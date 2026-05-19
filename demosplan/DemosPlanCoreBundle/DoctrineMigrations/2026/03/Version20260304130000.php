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
 * refs DPLAN-16766: Add phase_definition_id FK column to _statement as nullable.
 * Per-project migrations (Version20260304140001-140013) populate the column and
 * enforce NOT NULL afterward.
 */
final class Version20260304130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16766: Add nullable phase_definition_id FK column to _statement';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            'ALTER TABLE _statement
                ADD COLUMN phase_definition_id CHAR(36) DEFAULT NULL,
                ADD CONSTRAINT fk_st_phase_definition
                    FOREIGN KEY (phase_definition_id)
                    REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT'
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            'ALTER TABLE _statement
                DROP FOREIGN KEY fk_st_phase_definition,
                DROP COLUMN phase_definition_id'
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
