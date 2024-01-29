<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240105113406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T34551: Remove unique-constraint for procedures - customer
        regardless of collation';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $runOnDatabase = $this->connection->fetchOne('SELECT DATABASE()');
        $customerFk = $this->connection->fetchAllAssociative(
            'SELECT CONSTRAINT_NAME
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE REFERENCED_TABLE_SCHEMA = :currentDataBase
                 AND REFERENCED_TABLE_NAME = "customer"
                 AND TABLE_NAME = "_procedure"',
            ['currentDataBase' => $runOnDatabase]
        );
        $customerUnique = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT CU.CONSTRAINT_NAME, CU.CONSTRAINT_SCHEMA FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS TC, INFORMATION_SCHEMA.KEY_COLUMN_USAGE CU
                WHERE TC.CONSTRAINT_TYPE = "UNIQUE" AND TC.TABLE_NAME = "_procedure"
                AND CU.CONSTRAINT_NAME = TC.CONSTRAINT_NAME
                AND CU.COLUMN_NAME = "customer"
                AND CU.CONSTRAINT_SCHEMA = :currentDatabase',
            ['currentDatabase' => $runOnDatabase]
        );
        if (1 === count($customerFk)) {
            $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY '.$customerFk[0]['CONSTRAINT_NAME']);
        }
        if (1 === count($customerUnique)) {
            $this->addSql('ALTER TABLE _procedure DROP INDEX '.$customerUnique[0]['CONSTRAINT_NAME']);
        }
        $this->addSql('ALTER TABLE _procedure ADD CONSTRAINT FK_D1A01D0281398E09 FOREIGN KEY (customer) REFERENCES customer(_c_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $runOnDatabase = $this->connection->fetchOne('SELECT DATABASE()');

        $customerFk = $this->connection->fetchAllAssociative(
            'SELECT CONSTRAINT_NAME
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE REFERENCED_TABLE_SCHEMA = :currentDataBase
                 AND REFERENCED_TABLE_NAME = "customer"
                 AND TABLE_NAME = "_procedure"',
            ['currentDataBase' => $runOnDatabase]
        );

        $customerUnique = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT CU.CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS TC, INFORMATION_SCHEMA.KEY_COLUMN_USAGE CU
                WHERE TC.CONSTRAINT_TYPE = "UNIQUE" AND TC.TABLE_NAME = "_procedure"
                AND CU.CONSTRAINT_NAME = TC.CONSTRAINT_NAME
                AND CU.COLUMN_NAME = "customer"
                AND CU.CONSTRAINT_SCHEMA = :currentDatabase',
            ['currentDatabase' => $runOnDatabase]
        );
        if (0 === count($customerFk)) {
            $this->addSql('ALTER TABLE _procedure ADD CONSTRAINT FK_D1A01D0281398E09 FOREIGN KEY (customer) REFERENCES customer(_c_id)');
        }
        if (0 === count($customerUnique)) {
            $this->addSql('ALTER TABLE _procedure ADD UNIQUE UNIQ_D1A01D0281398E09(customer)');
        }
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
