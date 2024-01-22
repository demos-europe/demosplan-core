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
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

class TransactionService implements TransactionServiceInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->entityManager = $managerRegistry->getManager();
    }

    /**
     * Executes a given task inside a transaction and returns the result of the task.
     * If an exception is thrown inside the task then the transaction will be rolled back
     * and the received exception will be rethrown.
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
        try {
            $result = $task($this->entityManager);
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
