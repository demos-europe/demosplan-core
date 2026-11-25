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

class Version20261123093348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add ocx_api_token for the bearer tokens of OCX senders';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE ocx_api_token (id CHAR(36) NOT NULL, customer CHAR(36) NOT NULL, user_id CHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, token_prefix VARCHAR(12) NOT NULL, token_hash VARCHAR(255) NOT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX ocx_api_token_prefix_unique (token_prefix), INDEX IDX_E86E782781398E09 (customer), INDEX IDX_E86E7827A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ocx_api_token ADD CONSTRAINT FK_E86E782781398E09 FOREIGN KEY (customer) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ocx_api_token ADD CONSTRAINT FK_E86E7827A76ED395 FOREIGN KEY (user_id) REFERENCES _user (_u_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE ocx_api_token DROP FOREIGN KEY FK_E86E782781398E09');
        $this->addSql('ALTER TABLE ocx_api_token DROP FOREIGN KEY FK_E86E7827A76ED395');
        $this->addSql('DROP TABLE ocx_api_token');
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
