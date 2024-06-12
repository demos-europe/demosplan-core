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

class Version20231129120204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T: the email address property have to be of type a string, not any more a relation to EmailAddress and with no unique constraint.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('TRUNCATE support_contact');
        $this->addSql('ALTER TABLE support_contact DROP FOREIGN KEY FK_8C8C0928B08E074E');
        $this->addSql('DROP INDEX IDX_8C8C0928B08E074E ON support_contact');
        $this->addSql('ALTER TABLE support_contact CHANGE email_address email_address VARCHAR(255) NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('TRUNCATE support_contact');
        $this->addSql('ALTER TABLE support_contact CHANGE email_address email_address CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE support_contact ADD CONSTRAINT FK_8C8C0928B08E074E FOREIGN KEY (email_address) REFERENCES email_address (id)');
        $this->addSql('CREATE INDEX IDX_8C8C0928B08E074E ON support_contact (email_address)');
    }

    /**
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
