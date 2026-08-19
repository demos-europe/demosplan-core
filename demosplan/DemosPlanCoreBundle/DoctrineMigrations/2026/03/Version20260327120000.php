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

class Version20260327120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_password_history table to track previously used passwords';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $tableExists = $this->connection->fetchOne("SHOW TABLES LIKE 'user_password_history'");
        if (false !== $tableExists) {
            return;
        }
        $this->addSql('CREATE TABLE user_password_history (
        id VARCHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        hashed_password VARCHAR(255) NOT NULL,
        created_date DATETIME NOT NULL,
        INDEX IDX_72206399A76ED395 (user_id),
        PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE user_password_history ADD CONSTRAINT FK_72206399A76ED395 FOREIGN KEY (user_id) REFERENCES _user (_u_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->abortIf(
            !$schema->hasTable('user_password_history'),
            'Cannot migrate: Table user_password_history does not exist'
        );
        $this->addSql('ALTER TABLE user_password_history DROP FOREIGN KEY FK_72206399A76ED395');
        $this->addSql('DROP TABLE user_password_history');
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
