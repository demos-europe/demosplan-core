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
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230626135116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T:32796 create ProcedureMessage in relation with XBeteiligung ';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE procedure_message (id CHAR(36) NOT NULL, procedure_id CHAR(36) NOT NULL, message LONGTEXT NOT NULL, created_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, modification_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, error TINYINT(1) DEFAULT false NOT NULL, deleted TINYINT(1) DEFAULT false NOT NULL, request_count INT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_E7F5DA961624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE procedure_message ADD CONSTRAINT FK_E7F5DA961624BCD2 FOREIGN KEY (procedure_id) REFERENCES _procedure (_p_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE procedure_message DROP FOREIGN KEY FK_E7F5DA961624BCD2');
        $this->addSql('DROP TABLE procedure_message');
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
