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

class Version20260612132621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DPLAN-17744: add procedure_deletion_log table';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if ($schema->hasTable('procedure_deletion_log')) {
            return;
        }

        $this->addSql(
            'CREATE TABLE procedure_deletion_log (
                id                          CHAR(36)        NOT NULL,
                procedure_id                CHAR(36)        NOT NULL,
                procedure_name              VARCHAR(4096)   NOT NULL,
                is_blueprint                TINYINT(1)      NOT NULL,
                deleted_by_user_id          CHAR(36)        DEFAULT NULL,
                deleted_by_user_first_name  VARCHAR(255)    DEFAULT NULL,
                deleted_by_user_last_name   VARCHAR(255)    DEFAULT NULL,
                deleted_by_user_email       VARCHAR(255)    DEFAULT NULL,
                is_hard_deleted             TINYINT(1)      NOT NULL,
                deleted_at                  DATETIME        NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB'
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if (!$schema->hasTable('procedure_deletion_log')) {
            return;
        }

        $this->addSql('DROP TABLE procedure_deletion_log');
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
