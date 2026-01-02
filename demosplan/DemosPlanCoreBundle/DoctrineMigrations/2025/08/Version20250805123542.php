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

class Version20250805123542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16177: fill designated_switch_date_timestamp for existing procedure phases';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Update designated_switch_date_timestamp for existing procedure phases
        // that have a designated_switch_date but no designated_switch_date_timestamp
        $this->addSql('
            UPDATE procedure_phase
            SET designated_switch_date_timestamp = UNIX_TIMESTAMP(designated_switch_date)
            WHERE designated_switch_date IS NOT NULL
            AND designated_switch_date_timestamp IS NULL
        ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Rollback: Clear designated_switch_date_timestamp values that were populated by this migration
        // We can't perfectly rollback since we don't know which ones were originally null,
        // but we can clear values that match the designated_switch_date timestamp
        $this->addSql('
            UPDATE procedure_phase
            SET designated_switch_date_timestamp = NULL
            WHERE designated_switch_date IS NOT NULL
            AND designated_switch_date_timestamp = UNIX_TIMESTAMP(designated_switch_date)
        ');
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
