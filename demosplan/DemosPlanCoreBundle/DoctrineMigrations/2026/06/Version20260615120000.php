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

class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-18005: Fix default values for public participation publication and feedback flags';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // The DPLAN-16042 migration (Version20250703101620) introduced this column with
        // DEFAULT 0, contradicting the ORM annotation (DEFAULT 1) and BauGB requirements.
        // All rows received 0 at introduction time; there is no way to distinguish
        // intentional 0 from the bug-induced default, so all rows are corrected to 1.
        $this->addSql(<<<'SQL'
            ALTER TABLE _procedure_settings
                MODIFY _p_public_participation_feedback_enabled TINYINT(1) DEFAULT 1 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE _procedure_settings SET _p_public_participation_feedback_enabled = 1
        SQL);

        // Column default has been DEFAULT 1 since the initial 2020 migration.
        // Opt-in semantics are required for Bauleitplanverfahren (DPLAN-18005).
        // Only blueprint procedures are corrected — real procedures keep their
        // planner-configured values. Projects without
        // feature_toggle_public_participation_publication self-correct their blueprint
        // to 1 on the next edit save (hidden input always submits value=1).
        $this->addSql(<<<'SQL'
            ALTER TABLE _procedure
                MODIFY _p_public_participation_publication_enabled TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE _procedure
            SET _p_public_participation_publication_enabled = 0
            WHERE _p_master != 0
               OR master_template = 1
        SQL);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(<<<'SQL'
            ALTER TABLE _procedure_settings
                MODIFY _p_public_participation_feedback_enabled TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE _procedure_settings SET _p_public_participation_feedback_enabled = 0
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE _procedure
                MODIFY _p_public_participation_publication_enabled TINYINT(1) DEFAULT 1 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE _procedure
            SET _p_public_participation_publication_enabled = 1
            WHERE _p_master != 0
               OR master_template = 1
        SQL);
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
