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

class Version20250610134948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_access_control table for user-specific permissions';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(<<<'SQL'
            CREATE TABLE user_access_control (id CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, orga_id CHAR(36) NOT NULL, role_id CHAR(36) NOT NULL, customer_id CHAR(36) NOT NULL, permission VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, INDEX IDX_4E7B2907A76ED395 (user_id), INDEX IDX_4E7B290797F068A1 (orga_id), INDEX IDX_4E7B2907D60322AC (role_id), INDEX IDX_4E7B29079395C3F3 (customer_id), UNIQUE INDEX unique_user_orga_customer_role_permission (user_id, orga_id, customer_id, role_id, permission), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control ADD CONSTRAINT FK_4E7B2907A76ED395 FOREIGN KEY (user_id) REFERENCES _user (_u_id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control ADD CONSTRAINT FK_4E7B290797F068A1 FOREIGN KEY (orga_id) REFERENCES _orga (_o_id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control ADD CONSTRAINT FK_4E7B2907D60322AC FOREIGN KEY (role_id) REFERENCES _role (_r_id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control ADD CONSTRAINT FK_4E7B29079395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE
        SQL);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control DROP FOREIGN KEY FK_4E7B2907A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control DROP FOREIGN KEY FK_4E7B290797F068A1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control DROP FOREIGN KEY FK_4E7B2907D60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_access_control DROP FOREIGN KEY FK_4E7B29079395C3F3
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_access_control
        SQL);
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
