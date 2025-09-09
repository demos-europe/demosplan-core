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

class Version20231006133151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T34504: Create SupportContact Table and setup eMailAddress and customer relations';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE support_contact (id CHAR(36) NOT NULL, email_address CHAR(36) DEFAULT NULL, customer CHAR(36) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, phone_number VARCHAR(255) DEFAULT NULL, text LONGTEXT DEFAULT NULL, visible TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX customer_title_unique (customer, title), INDEX IDX_8C8C0928B08E074E (email_address), INDEX IDX_8C8C092881398E09 (customer), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE support_contact ADD CONSTRAINT FK_8C8C0928B08E074E FOREIGN KEY (email_address) REFERENCES email_address (id)');
        $this->addSql('ALTER TABLE support_contact ADD CONSTRAINT FK_8C8C092881398E09 FOREIGN KEY (customer) REFERENCES customer (_c_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE support_contact DROP FOREIGN KEY FK_8C8C0928B08E074E');
        $this->addSql('ALTER TABLE support_contact DROP FOREIGN KEY FK_8C8C092881398E09');
        $this->addSql('DROP TABLE support_contact');
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
