<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Services\TransactionServiceInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\Exception\UnmanagedTransactionException;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TransactionService implements TransactionServiceInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * Events registered via {@see dispatchAfterCommit()} that are waiting for the outermost
     * transaction to commit. Each entry holds the event and, for addon-facing events, the
     * contract interface it is dispatched under.
     *
     * @var array<int, array{event: DPlanEvent, eventInterface: class-string|null}>
     */
    private array $pendingPostCommitEvents = [];

    /**
     * How many {@see executeAndFlushInTransaction()} calls are currently running, nested into each other.
     * Tracked separately from the connection's nesting level, so transactions opened elsewhere
     * can be told apart from the ones this service will commit and dispatch for.
     */
    private int $runningTransactionDepth = 0;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->entityManager = $managerRegistry->getManager();
    }

    /**
     * Executes a given task inside a transaction and returns the result of the task.
     * If an exception is thrown inside the task then the transaction will be rolled back
     * and the received exception will be rethrown.
     *
     * Events registered via {@see dispatchAfterCommit()} while the task runs are dispatched
     * once the outermost transaction has committed. Events registered by a task that does not
     * commit are discarded, whether it fails with an exception or an error, and whether it is
     * the outermost task or one nested into a task that carries on.
     *
     * The dispatch happens after the transaction handling, so an exception thrown by a listener
     * reaches the caller as is and cannot trigger a rollback of an already committed transaction.
     *
     * @template T
     *
     * @phpstan-param callable(EntityManager): T $task
     *
     * @phpstan-return T
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ConnectionException
     */
    public function executeAndFlushInTransaction(callable $task)
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        ++$this->runningTransactionDepth;
        $pendingEventCountAtStart = count($this->pendingPostCommitEvents);
        $committed = false;
        try {
            $result = $task($this->entityManager);
            $this->entityManager->flush();
            $connection->commit();
            $committed = true;
        } catch (Exception $e) {
            $this->entityManager->rollback();

            throw $e;
        } finally {
            --$this->runningTransactionDepth;
            if (!$committed) {
                // drop only what this task registered; an enclosing task that carries on keeps its own events
                array_splice($this->pendingPostCommitEvents, $pendingEventCountAtStart);
            }
        }

        if (0 === $this->runningTransactionDepth) {
            $this->dispatchPendingPostCommitEvents();
        }

        return $result;
    }

    /**
     * Dispatches an event only once the change it announces is committed. Use this for events whose
     * listeners act outside the current database transaction, so a later rollback can never leave
     * them with a record of a change that was never persisted.
     *
     * Inside a running {@see executeAndFlushInTransaction()} task the event is queued and dispatched
     * after the outermost task has committed; nested tasks share the queue of the outermost one.
     * Outside any transaction there is nothing to wait for and the event is dispatched right away.
     *
     * Restricted to {@see DPlanEvent} on purpose: framework events like Doctrine lifecycle or
     * HTTP kernel events describe a state that is gone by the time the commit happens.
     *
     * @param class-string|null $eventInterface the addon contract interface the event is dispatched under,
     *                                          as that is the name addon listeners subscribe to; omit for
     *                                          core-internal events, which are dispatched under their class name
     *
     * @throws UnmanagedTransactionException when a transaction is open on the connection that was not started by
     *                                       {@see executeAndFlushInTransaction()}, as this service would never
     *                                       learn about its commit and the event would never be dispatched
     */
    public function dispatchAfterCommit(DPlanEvent $event, ?string $eventInterface = null): void
    {
        if ($this->runningTransactionDepth > 0) {
            $this->pendingPostCommitEvents[] = ['event' => $event, 'eventInterface' => $eventInterface];

            return;
        }

        if ($this->entityManager->getConnection()->isTransactionActive()) {
            throw new UnmanagedTransactionException(sprintf('%s cannot be dispatched after commit: the open transaction was not started via %s and its commit is invisible to this service.', $event::class, self::class));
        }

        $this->eventDispatcher->dispatch($event, $eventInterface);
    }

    private function dispatchPendingPostCommitEvents(): void
    {
        // detach the queue first, so listeners starting their own transaction cannot re-dispatch these events
        $pendingEvents = $this->pendingPostCommitEvents;
        $this->pendingPostCommitEvents = [];

        foreach ($pendingEvents as $pendingEvent) {
            $this->eventDispatcher->dispatch($pendingEvent['event'], $pendingEvent['eventInterface']);
        }
    }
}
