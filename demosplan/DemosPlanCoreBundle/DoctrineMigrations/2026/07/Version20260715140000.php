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

class Version20260715140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs HDDP-27: add assessment_table_export_job for asynchronous Abwägungstabelle exports';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            CREATE TABLE assessment_table_export_job (
                id CHAR(36) NOT NULL,
                status VARCHAR(20) NOT NULL,
                procedure_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                file_hash CHAR(36) DEFAULT NULL,
                file_name VARCHAR(255) DEFAULT NULL,
                error_message LONGTEXT DEFAULT NULL,
                created_date DATETIME NOT NULL,
                modified_date DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB
        ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE assessment_table_export_job');
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
