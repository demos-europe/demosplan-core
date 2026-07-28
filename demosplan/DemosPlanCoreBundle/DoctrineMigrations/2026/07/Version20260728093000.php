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

class Version20260728093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs HDDP-31: add element_import_job for asynchronous Planunterlagen imports';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            CREATE TABLE element_import_job (
                id CHAR(36) NOT NULL,
                status VARCHAR(20) NOT NULL,
                phase VARCHAR(20) NOT NULL,
                procedure_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                files_total INT DEFAULT 0 NOT NULL,
                files_processed INT DEFAULT 0 NOT NULL,
                import_list LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\',
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

        $this->addSql('DROP TABLE element_import_job');
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
