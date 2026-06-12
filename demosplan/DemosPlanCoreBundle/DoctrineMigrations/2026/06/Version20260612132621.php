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

        $this->addSql('CREATE TABLE procedure_deletion_log (id CHAR(36) NOT NULL, procedure_fk CHAR(36) DEFAULT NULL, procedure_id CHAR(36) NOT NULL, procedure_name VARCHAR(4096) NOT NULL, is_blueprint TINYINT(1) NOT NULL, deleted_by_user_id CHAR(36) DEFAULT NULL, deleted_by_user_first_name VARCHAR(255) DEFAULT NULL, deleted_by_user_last_name VARCHAR(255) DEFAULT NULL, deleted_by_user_email VARCHAR(255) DEFAULT NULL, delete_type VARCHAR(10) NOT NULL, deleted_at DATETIME NOT NULL, INDEX IDX_E7867334103BD8C (procedure_fk), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE procedure_deletion_log ADD CONSTRAINT FK_E7867334103BD8C FOREIGN KEY (procedure_fk) REFERENCES _procedure (_p_id) ON DELETE SET NULL');
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

        $this->addSql('ALTER TABLE procedure_deletion_log DROP FOREIGN KEY FK_E7867334103BD8C');
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
