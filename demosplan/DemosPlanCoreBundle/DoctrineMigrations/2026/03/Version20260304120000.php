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
 * refs DPLAN-16766: Finalise procedure_phase FK migration.
 * After data migrations (Version20260304110001-110013) have linked all rows, enforce NOT NULL
 * on phase_definition_id and drop the legacy phase_key / designated_phase string columns.
 */
final class Version20260304120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16766: Make phase_definition_id NOT NULL and drop legacy phase_key / designated_phase columns';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            'ALTER TABLE procedure_phase
                MODIFY COLUMN phase_definition_id CHAR(36) NOT NULL,
                DROP COLUMN phase_key,
                DROP COLUMN designated_phase'
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Re-add the dropped columns. The original key values cannot be restored
        // automatically; run the per-project data migrations (Version20260304110001-110013)
        // in reverse order to restore data if needed.
        $this->addSql(
            "ALTER TABLE procedure_phase
                MODIFY COLUMN phase_definition_id CHAR(36) DEFAULT NULL,
                ADD COLUMN phase_key VARCHAR(50) NOT NULL DEFAULT 'configuration' AFTER designated_phase_definition_id,
                ADD COLUMN designated_phase VARCHAR(50) DEFAULT NULL AFTER phase_key"
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
