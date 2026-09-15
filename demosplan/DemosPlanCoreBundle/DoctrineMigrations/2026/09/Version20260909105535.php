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

class Version20260909105535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T: DPLAN-18153 Geplante Synopsenexporte speichern und ablegen, adds feature for scheduled xlsx export';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE export_schedule (id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, procedure_id CHAR(36) NOT NULL, parameters LONGTEXT NOT NULL, parameters_hash CHAR(64) NOT NULL, frequency VARCHAR(20) NOT NULL, weekday SMALLINT DEFAULT NULL, day_of_month SMALLINT DEFAULT NULL, next_run_at DATETIME NOT NULL, last_run_at DATETIME DEFAULT NULL, created_date DATETIME NOT NULL, modified_date DATETIME NOT NULL, INDEX export_schedule_due_lookup (next_run_at), INDEX export_schedule_user_lookup (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scheduled_export_job (id CHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, user_id CHAR(36) NOT NULL, parameters_hash CHAR(64) NOT NULL, file_hash CHAR(36) DEFAULT NULL, file_name VARCHAR(255) DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, created_date DATETIME NOT NULL, modified_date DATETIME NOT NULL, schedule_id CHAR(36) NOT NULL, procedure_id CHAR(36) NOT NULL, delete_after DATETIME DEFAULT NULL, INDEX scheduled_export_job_schedule_lookup (schedule_id, status), INDEX scheduled_export_job_status_modified (status, modified_date), INDEX scheduled_export_job_delete_after (delete_after), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE export_schedule');
        $this->addSql('DROP TABLE scheduled_export_job');
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
