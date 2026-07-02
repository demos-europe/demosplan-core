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
 * refs DPLAN-17884: Widen deprecated phase columns from VARCHAR(50) to VARCHAR(255).
 * These columns are synced from ProcedurePhaseDefinition::getName(), whose VARCHAR(255)
 * names can exceed 50 chars (e.g. "Frühzeitige Beteiligung Öffentlichkeit - § 3 (1) BauGB"),
 * which previously caused DraftStatement creation to fail.
 */
final class Version20260528093230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-17884: Widen deprecated phase-name columns (_statement._st_phase, _draft_statement._ds_phase, _draft_statement_versions._ds_phase, institution_mail._p_phase, procedure_phase.designated_phase) from VARCHAR(50) to VARCHAR(255).';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _statement CHANGE _st_phase _st_phase VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE _draft_statement CHANGE _ds_phase _ds_phase VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE _draft_statement_versions CHANGE _ds_phase _ds_phase VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE institution_mail CHANGE _p_phase _p_phase VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE procedure_phase CHANGE designated_phase designated_phase VARCHAR(255) DEFAULT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Truncate any values exceeding the original VARCHAR(50) limit before shrinking
        // the columns, otherwise the ALTER TABLE would fail with "Data too long" on rows
        // that were created while the up migration was active.
        $this->addSql('UPDATE _statement SET _st_phase = LEFT(_st_phase, 50) WHERE CHAR_LENGTH(_st_phase) > 50');
        $this->addSql('UPDATE _draft_statement SET _ds_phase = LEFT(_ds_phase, 50) WHERE CHAR_LENGTH(_ds_phase) > 50');
        $this->addSql('UPDATE _draft_statement_versions SET _ds_phase = LEFT(_ds_phase, 50) WHERE CHAR_LENGTH(_ds_phase) > 50');
        $this->addSql('UPDATE institution_mail SET _p_phase = LEFT(_p_phase, 50) WHERE CHAR_LENGTH(_p_phase) > 50');
        $this->addSql('UPDATE procedure_phase SET designated_phase = LEFT(designated_phase, 50) WHERE CHAR_LENGTH(designated_phase) > 50');

        $this->addSql('ALTER TABLE _statement CHANGE _st_phase _st_phase VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE _draft_statement CHANGE _ds_phase _ds_phase VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE _draft_statement_versions CHANGE _ds_phase _ds_phase VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE institution_mail CHANGE _p_phase _p_phase VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE procedure_phase CHANGE designated_phase designated_phase VARCHAR(50) DEFAULT NULL');
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
