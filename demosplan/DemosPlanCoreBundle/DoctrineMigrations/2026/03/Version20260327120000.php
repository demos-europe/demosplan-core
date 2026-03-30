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

        $this->addSql('
              CREATE TABLE user_password_history (
                  id CHAR(36) NOT NULL,
                  user_id CHAR(36) NOT NULL,
                  hashed_password VARCHAR(255) NOT NULL,
                  created_date DATETIME NOT NULL,
                  PRIMARY KEY (id),
                  INDEX IDX_user_password_history_user (user_id),
                  CONSTRAINT FK_user_password_history_user
                      FOREIGN KEY (user_id) REFERENCES _user (_u_id) ON DELETE CASCADE
              ) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB
          ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE IF EXISTS user_password_history');
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
