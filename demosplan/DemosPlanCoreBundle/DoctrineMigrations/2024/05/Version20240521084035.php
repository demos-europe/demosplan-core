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

class Version20240521084035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-11483: Create permissions table to create permissions dynamically for orga - customer - role combinations.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE access_control_permission (id CHAR(36) NOT NULL, orga_id CHAR(36) DEFAULT NULL, role_id CHAR(36) DEFAULT NULL, customer_id CHAR(36) DEFAULT NULL, permission VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, INDEX IDX_5B3A1C1497F068A1 (orga_id), INDEX IDX_5B3A1C14D60322AC (role_id), INDEX IDX_5B3A1C149395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_control_permission ADD CONSTRAINT FK_5B3A1C1497F068A1 FOREIGN KEY (orga_id) REFERENCES _orga (_o_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_control_permission ADD CONSTRAINT FK_5B3A1C14D60322AC FOREIGN KEY (role_id) REFERENCES _role (_r_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_control_permission ADD CONSTRAINT FK_5B3A1C149395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE access_control_permission DROP FOREIGN KEY FK_5B3A1C1497F068A1');
        $this->addSql('ALTER TABLE access_control_permission DROP FOREIGN KEY FK_5B3A1C14D60322AC');
        $this->addSql('ALTER TABLE access_control_permission DROP FOREIGN KEY FK_5B3A1C149395C3F3');
        $this->addSql('DROP TABLE access_control_permission');
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
