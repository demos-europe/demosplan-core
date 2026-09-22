<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\Exception\UnmanagedTransactionException;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use Doctrine\ORM\EntityManager;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\Base\FunctionalTestCase;

class TransactionServiceTest extends FunctionalTestCase
{
    private const CUSTOM_EVENT_NAME = 'test.transaction_service.custom_event_name';

    protected ?TransactionService $sut = null;

    protected ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * The names the events reached the listeners under, in dispatch order.
     *
     * @var array<int, string>|null
     */
    protected ?array $dispatchedEventNames = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(TransactionService::class);
        $this->eventDispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        $this->dispatchedEventNames = [];
    }

    public function testDispatchesRegisteredEventOnlyAfterTransactionCommitted(): void
    {
        $event = $this->createEvent();
        $this->recordDispatchesOf($event::class);

        $this->sut->executeAndFlushInTransaction(function () use ($event): void {
            $this->sut->dispatchAfterCommit($event);

            self::assertSame([], $this->dispatchedEventNames, 'must not dispatch while the transaction is still open');
        });

        self::assertSame([$event::class], $this->dispatchedEventNames);
    }

    public function testDispatchesEventsOfNestedTransactionsAfterOutermostCommit(): void
    {
        $event = $this->createEvent();
        $this->recordDispatchesOf($event::class);

        $this->sut->executeAndFlushInTransaction(function () use ($event): void {
            $this->sut->executeAndFlushInTransaction(function () use ($event): void {
                $this->sut->dispatchAfterCommit($event);
            });

            self::assertSame([], $this->dispatchedEventNames, 'the inner commit is nested and must not dispatch');
        });

        self::assertSame([$event::class], $this->dispatchedEventNames);
    }

    public function testDiscardsRegisteredEventsWhenTransactionRollsBack(): void
    {
        $event = $this->createEvent();
        $this->recordDispatchesOf($event::class);

        try {
            $this->sut->executeAndFlushInTransaction(function () use ($event): void {
                $this->sut->dispatchAfterCommit($event);

                throw new RuntimeException('forces the rollback');
            });
            self::fail('the exception of the task must be rethrown');
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame([], $this->dispatchedEventNames, 'a rolled back change must not be announced');

        // the discarded event must not resurface with the next transaction either
        $this->sut->executeAndFlushInTransaction(static fn (EntityManager $entityManager): null => null);

        self::assertSame([], $this->dispatchedEventNames);
    }

    public function testDispatchesImmediatelyWithoutOpenTransaction(): void
    {
        $event = $this->createEvent();
        $this->recordDispatchesOf($event::class);

        $this->sut->dispatchAfterCommit($event);

        self::assertSame([$event::class], $this->dispatchedEventNames);
    }

    public function testDispatchesUnderGivenEventInterfaceName(): void
    {
        $event = $this->createEvent();
        $this->recordDispatchesOf($event::class);
        $this->recordDispatchesOf(self::CUSTOM_EVENT_NAME);

        $this->sut->executeAndFlushInTransaction(function () use ($event): void {
            $this->sut->dispatchAfterCommit($event, self::CUSTOM_EVENT_NAME);
        });

        self::assertSame([self::CUSTOM_EVENT_NAME], $this->dispatchedEventNames, 'must be dispatched under the given name only');
    }

    public function testRejectsRegistrationInsideTransactionNotStartedByService(): void
    {
        $event = $this->createEvent();
        $this->recordDispatchesOf($event::class);
        $connection = $this->getEntityManager()->getConnection();

        $connection->beginTransaction();
        try {
            $this->expectException(UnmanagedTransactionException::class);
            $this->sut->dispatchAfterCommit($event);
        } finally {
            $connection->rollBack();
        }
    }

    private function createEvent(): DPlanEvent
    {
        return new class extends DPlanEvent {
        };
    }

    private function recordDispatchesOf(string $eventName): void
    {
        $this->eventDispatcher->addListener(
            $eventName,
            function (object $event, string $dispatchedEventName): void {
                $this->dispatchedEventNames[] = $dispatchedEventName;
            }
        );
    }
}
