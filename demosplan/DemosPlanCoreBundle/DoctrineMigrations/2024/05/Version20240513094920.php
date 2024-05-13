<?php

declare(strict_types = 1);

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

class Version20240513094920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-11483: Create permissions table to create permissions dynamically for orga based on the user role';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            CREATE TABLE access_control_permissions
                (id CHAR(36) NOT NULL,
                permission VARCHAR(255) NOT NULL,
                orga_id CHAR(36) DEFAULT NULL,
                role_id CHAR(36) NOT NULL,
                customer_id CHAR(36) NOT NULL,
                creation_date DATETIME NOT NULL,
                modification_date DATETIME NOT NULL,
                deletion_date DATETIME NOT NULL,
            PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE access_control_permissions
                ADD CONSTRAINT FK_52C66981CBD85678 FOREIGN KEY (orga_id)
                REFERENCES _orga (_o_id)
                ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE access_control_permissions
                ADD CONSTRAINT FK_52C66981CBD80987 FOREIGN KEY (role_id)
                REFERENCES _role (_r_id)
                ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE access_control_permissions
                ADD CONSTRAINT FK_52C66981CBD234 FOREIGN KEY (customer_id)
                REFERENCES customer (_c_id)
                ON DELETE SET NULL
        ');

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE access_control_permissions');

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
