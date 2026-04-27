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

class Version20260423104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop stale UNIQUE index on _procedure.customer that survived Version20240105113406 '
            .'on some environments and still enforces a 1:1 procedure-customer relation, '
            .'causing duplicate-key errors on procedure creation.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $procedureTable = $schema->getTable('_procedure');

        if (!$procedureTable->hasIndex('UNIQ_D1A01D0281398E09')) {
            return;
        }

        // FK_D1A01D0281398E09 currently relies on UNIQ_… as its backing index;
        // MySQL would refuse to drop it without another usable index on `customer`.
        if (!$procedureTable->hasIndex('IDX_D1A01D0281398E09')) {
            $this->addSql('CREATE INDEX IDX_D1A01D0281398E09 ON _procedure (customer)');
        }

        $this->addSql('ALTER TABLE _procedure DROP INDEX UNIQ_D1A01D0281398E09');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->write('Down migration not supported — restoring the unique constraint would fail on customers with multiple procedures.');
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
