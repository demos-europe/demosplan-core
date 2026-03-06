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

class Version20260123163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organisation_id to import_job table to support multi-responsibility context preservation';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE import_job ADD organisation_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE import_job ADD CONSTRAINT FK_BD9DCADC9E6B1585 FOREIGN KEY (organisation_id) REFERENCES _orga (_o_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BD9DCADC9E6B1585 ON import_job (organisation_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE import_job DROP FOREIGN KEY FK_BD9DCADC9E6B1585');
        $this->addSql('DROP INDEX IDX_BD9DCADC9E6B1585 ON import_job');
        $this->addSql('ALTER TABLE import_job DROP organisation_id');
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
