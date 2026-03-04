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

/**
 * refs DPLAN-16766: Finalise _statement FK migration.
 * After data migrations (Version20260304140001-140013) have linked all rows, enforce NOT NULL
 * on phase_definition_id and drop the legacy _st_phase string column.
 */
final class Version20260304150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16766: Make _statement.phase_definition_id NOT NULL and drop legacy _st_phase column';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            'ALTER TABLE _statement
                MODIFY COLUMN phase_definition_id CHAR(36) NOT NULL,
                DROP COLUMN _st_phase'
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Re-add the dropped column. The original _st_phase values cannot be restored
        // automatically; run the per-project data migrations (Version20260304140001-140013)
        // in reverse order to restore data if needed.
        $this->addSql(
            "ALTER TABLE _statement
                MODIFY COLUMN phase_definition_id CHAR(36) DEFAULT NULL,
                ADD COLUMN _st_phase VARCHAR(50) NOT NULL DEFAULT 'configuration' AFTER phase_definition_id"
        );
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
