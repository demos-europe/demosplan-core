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

class Version20230711125038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs 32796 create ProcedureMessage in relation with XBeteiligung ';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE procedure_message DROP FOREIGN KEY FK_E7F5DA961624BCD2');
        $this->addSql('DROP INDEX UNIQ_E7F5DA961624BCD2 ON procedure_message');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // the table needs to be truncated before adding back the unique constraint
        // as there could be multiple meassages regarding the same procedure - as intended.
        $this->addSql('TRUNCATE TABLE procedure_message');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E7F5DA961624BCD2 ON procedure_message (procedure_id)');
        $this->addSql('ALTER TABLE procedure_message ADD CONSTRAINT FK_E7F5DA961624BCD2 FOREIGN KEY (procedure_id) REFERENCES _procedure (_p_id)');
        $this->abortIfNotMysql();
    }

    /**S
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySqlPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
