<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Queries;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Exception;

class SqlQueriesService extends CoreService
{
    public function __construct(private readonly Connection $dbConnection) {
    }

    public function getConnection(): Connection
    {
        return $this->dbConnection;
    }

    /**
     * @throws Exception
     */
    public function deleteFromTableByIdentifierArray(string $tableName, string $identifier, array $ids, bool $isDryRun = false): void
    {
        if (!$this->doesTableExist($tableName)) {
            throw new Exception("No table with the name $tableName exists in this database. Data could not be fetched.");
        }

        $deletionQueryBuilder = $this->dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete($tableName)
            ->where($identifier.' IN (:idList)')
            ->setParameter('idList', $ids, ArrayParameterType::STRING);

        if ($isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function fetchFromTableByParameter(array $targetColumns, string $tableName, string $identifier, array $parameter): array
    {
        if (!$this->doesTableExist($tableName)) {
            throw new Exception("No table with the name $tableName exists in this database. Data could not be fetched.");
        }

        $fetchQueryBuilder = $this->dbConnection->createQueryBuilder();
        $fetchQueryBuilder
            ->select(...$targetColumns)
            ->from($tableName)
            ->where($identifier.' IN (:idList)')
            ->setParameter('idList', $parameter, ArrayParameterType::STRING);

        return $fetchQueryBuilder->fetchAllAssociative();
    }

    /**
     * This is necessary to even allow us to delete all tables individually.
     * @throws Exception
     */
    public function deactivateForeignKeyChecks(): void
    {
        $this->dbConnection->executeStatement('SET foreign_key_checks = 0;');
    }

    /**
     * @throws Exception
     */
    public function activateForeignKeyChecks(): void
    {
        $this->dbConnection->executeStatement('SET foreign_key_checks = 1;');
    }

    /**
     * @throws Exception
     */
    public function doesTableExist(string $tableName): bool
    {
        return $this->dbConnection->createSchemaManager()->tablesExist([$tableName]);
    }

    /**
     * @throws Exception
     */
    public function CheckColumnInTable(string $tableName, string $columnName): bool
    {
        if (!$this->doesTableExist($tableName)) {
            throw new Exception("No table with the name $tableName exists in this database. Data could not be fetched.");
        }

        $tableColumns = $this->dbConnection->createSchemaManager()->listTableColumns($tableName);

        if (in_array($columnName, $tableColumns)) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        $this->dbConnection->beginTransaction();
    }

    /**
     * @throws Exception
     */
    public function commitTransaction(): void
    {
        $this->dbConnection->commit();
    }

    /**
     * @throws Exception
     */
    public function rollbackTransaction(): void
    {
        $this->dbConnection->rollBack();
    }
}
