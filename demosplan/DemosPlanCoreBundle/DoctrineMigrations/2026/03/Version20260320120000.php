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

class Version20260320120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add identity_provider_type and auto_provision_users columns to customer_oauth_config';
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

        $this->addSql("ALTER TABLE customer_oauth_config ADD identity_provider_type VARCHAR(30) NOT NULL DEFAULT 'keycloak'");
        $this->addSql('ALTER TABLE customer_oauth_config ADD auto_provision_users TINYINT(1) NOT NULL DEFAULT 0');

        // Backfill: detect existing Azure configs by URL
        $this->addSql("UPDATE customer_oauth_config SET identity_provider_type = 'azure_entra_id' WHERE keycloak_auth_server_url LIKE '%login.microsoftonline.com%'");

        // Backfill: set auto-provision only for Azure configs that had defaultOrganisation set
        // (only Azure flow supports auto-provisioning; a Keycloak config with a default org would be a no-op)
        $this->addSql("UPDATE customer_oauth_config SET auto_provision_users = 1 WHERE default_organisation_id IS NOT NULL AND keycloak_auth_server_url LIKE '%login.microsoftonline.com%'");
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE customer_oauth_config DROP COLUMN auto_provision_users');
        $this->addSql('ALTER TABLE customer_oauth_config DROP COLUMN identity_provider_type');
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
