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

class Version20260401144146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create recommendation_version table for tracking recommendation snapshots';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if ($schema->hasTable('recommendation_version')) {
            return;
        }

        $this->addSql('CREATE TABLE recommendation_version (id CHAR(36) NOT NULL, statement_id CHAR(36) NOT NULL, version_number INT UNSIGNED NOT NULL, recommendation_text MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_E27FDA6F849CB65B (statement_id), UNIQUE INDEX unique_statement_version (statement_id, version_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE recommendation_version ADD CONSTRAINT FK_E27FDA6F849CB65B FOREIGN KEY (statement_id) REFERENCES _statement (_st_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if (!$schema->hasTable('recommendation_version')) {
            return;
        }

        $this->addSql('DROP TABLE recommendation_version');
    }

    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            'Migration can only be executed safely on MySQL.'
        );
    }
}
