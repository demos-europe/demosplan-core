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

class Version20260424120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add personal_access_token table for long-lived, scoped, revocable API credentials';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $tableExists = $this->connection->fetchOne("SHOW TABLES LIKE 'personal_access_token'");
        if (false !== $tableExists) {
            return;
        }

        $this->addSql('CREATE TABLE personal_access_token (
            id VARCHAR(36) NOT NULL,
            user CHAR(36) NOT NULL,
            customer CHAR(36) NOT NULL,
            name VARCHAR(120) NOT NULL,
            token_prefix VARCHAR(12) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            scopes JSON NOT NULL,
            procedure_ids JSON DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            last_used_at DATETIME DEFAULT NULL,
            revoked_at DATETIME DEFAULT NULL,
            revoked_by CHAR(36) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX pat_token_prefix_unique (token_prefix),
            INDEX IDX_pat_user (user),
            INDEX IDX_pat_customer (customer),
            INDEX IDX_pat_revoked_by (revoked_by),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE personal_access_token
            ADD CONSTRAINT FK_pat_user FOREIGN KEY (user) REFERENCES _user (_u_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personal_access_token
            ADD CONSTRAINT FK_pat_customer FOREIGN KEY (customer) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personal_access_token
            ADD CONSTRAINT FK_pat_revoked_by FOREIGN KEY (revoked_by) REFERENCES _user (_u_id) ON DELETE SET NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->abortIf(
            !$schema->hasTable('personal_access_token'),
            'Cannot migrate: Table personal_access_token does not exist'
        );
        $this->addSql('ALTER TABLE personal_access_token DROP FOREIGN KEY FK_pat_revoked_by');
        $this->addSql('ALTER TABLE personal_access_token DROP FOREIGN KEY FK_pat_customer');
        $this->addSql('ALTER TABLE personal_access_token DROP FOREIGN KEY FK_pat_user');
        $this->addSql('DROP TABLE personal_access_token');
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
