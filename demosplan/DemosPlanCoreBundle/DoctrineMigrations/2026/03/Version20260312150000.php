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

class Version20260312150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default_organisation_id to customer_oauth_config for Azure user auto-provisioning';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $columnExists = $this->connection->fetchOne("SHOW COLUMNS FROM customer_oauth_config LIKE 'default_organisation_id'");
        if (false === $columnExists) {
            $this->addSql('ALTER TABLE customer_oauth_config ADD default_organisation_id CHAR(36) DEFAULT NULL');
            $this->addSql('ALTER TABLE customer_oauth_config ADD CONSTRAINT FK_customer_oauth_default_org FOREIGN KEY (default_organisation_id) REFERENCES orga (_o_id) ON DELETE SET NULL');
        }
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE customer_oauth_config DROP FOREIGN KEY FK_customer_oauth_default_org');
        $this->addSql('ALTER TABLE customer_oauth_config DROP COLUMN default_organisation_id');
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
