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

class Version20240912141620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-11379: Add customer_id to _platform_content table';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _platform_content ADD customer_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE _platform_content ADD CONSTRAINT FK_42348F4F9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_42348F4F9395C3F3 ON _platform_content (customer_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _platform_content DROP FOREIGN KEY FK_42348F4F9395C3F3');
        $this->addSql('DROP INDEX IDX_42348F4F9395C3F3 ON _platform_content');
        $this->addSql('ALTER TABLE _platform_content DROP customer_id');
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
