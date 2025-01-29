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

class Version20240606084357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-11483: Create access control table to store permissions dynamically for orga - customer - role combinations.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE access_control (id CHAR(36) NOT NULL, orga_id CHAR(36) NOT NULL, role_id CHAR(36) NOT NULL, customer_id CHAR(36) NOT NULL, permission VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, INDEX IDX_25FEF65E97F068A1 (orga_id), INDEX IDX_25FEF65ED60322AC (role_id), INDEX IDX_25FEF65E9395C3F3 (customer_id), UNIQUE INDEX unique_orga_customer_role_permission (orga_id, customer_id, role_id, permission), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_control ADD CONSTRAINT FK_25FEF65E97F068A1 FOREIGN KEY (orga_id) REFERENCES _orga (_o_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_control ADD CONSTRAINT FK_25FEF65ED60322AC FOREIGN KEY (role_id) REFERENCES _role (_r_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_control ADD CONSTRAINT FK_25FEF65E9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE access_control DROP FOREIGN KEY FK_25FEF65E97F068A1');
        $this->addSql('ALTER TABLE access_control DROP FOREIGN KEY FK_25FEF65ED60322AC');
        $this->addSql('ALTER TABLE access_control DROP FOREIGN KEY FK_25FEF65E9395C3F3');
        $this->addSql('DROP TABLE access_control');
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
