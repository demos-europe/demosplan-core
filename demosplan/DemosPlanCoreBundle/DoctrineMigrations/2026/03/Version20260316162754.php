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

class Version20260316162754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure FK constraint on customer_oauth_config.default_organisation_id exists with correct table reference';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $tableExists = $this->connection->fetchOne("SHOW TABLES LIKE 'customer_oauth_config'");
        if (false === $tableExists) {
            return;
        }

        $fkExists = $this->connection->fetchOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_oauth_config' AND CONSTRAINT_NAME = 'FK_customer_oauth_default_org'"
        );
        if (false === $fkExists) {
            $this->addSql('ALTER TABLE customer_oauth_config ADD CONSTRAINT FK_customer_oauth_default_org FOREIGN KEY (default_organisation_id) REFERENCES _orga (_o_id) ON DELETE SET NULL');
        }
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE customer_oauth_config DROP FOREIGN KEY FK_customer_oauth_default_org');
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
