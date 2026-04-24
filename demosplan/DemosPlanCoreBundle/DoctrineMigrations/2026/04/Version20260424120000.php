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

        // SQL matches the output of `bin/console doctrine:migrations:diff` for the entity
        // demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken. Keeping it verbatim
        // (including index-name hashes and DC2Type comments) so future diffs stay quiet.
        $this->addSql("CREATE TABLE personal_access_token (id CHAR(36) NOT NULL, user CHAR(36) NOT NULL, customer CHAR(36) NOT NULL, revoked_by CHAR(36) DEFAULT NULL, name VARCHAR(120) NOT NULL, token_prefix VARCHAR(12) NOT NULL, token_hash VARCHAR(255) NOT NULL, scopes JSON NOT NULL COMMENT '(DC2Type:json)', procedure_ids JSON DEFAULT NULL COMMENT '(DC2Type:json)', expires_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_5017171A8D93D649 (user), INDEX IDX_5017171A81398E09 (customer), INDEX IDX_5017171A8E5493E3 (revoked_by), UNIQUE INDEX pat_token_prefix_unique (token_prefix), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE personal_access_token ADD CONSTRAINT FK_5017171A8D93D649 FOREIGN KEY (user) REFERENCES _user (_u_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personal_access_token ADD CONSTRAINT FK_5017171A81398E09 FOREIGN KEY (customer) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personal_access_token ADD CONSTRAINT FK_5017171A8E5493E3 FOREIGN KEY (revoked_by) REFERENCES _user (_u_id) ON DELETE SET NULL');
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
        $this->addSql('ALTER TABLE personal_access_token DROP FOREIGN KEY FK_5017171A8D93D649');
        $this->addSql('ALTER TABLE personal_access_token DROP FOREIGN KEY FK_5017171A81398E09');
        $this->addSql('ALTER TABLE personal_access_token DROP FOREIGN KEY FK_5017171A8E5493E3');
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
