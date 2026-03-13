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

/**
 * Corrective migration: ensures customer_oauth_config and import_job use utf8mb3_unicode_ci.
 *
 * On MariaDB servers where old_mode does NOT include UTF8_IS_UTF8MB3, the original
 * migration's ambiguous "UTF8" charset was interpreted as utf8mb4 — causing a foreign key
 * mismatch with customer._c_id (utf8mb3). This migration handles three possible states:
 *
 * 1. Table doesn't exist (original migration failed): creates it with all columns from
 *    the three subsequent migrations and marks those migrations as executed.
 * 2. Table exists with utf8mb4: converts to utf8mb3_unicode_ci.
 * 3. Table exists with utf8mb3: no-op.
 *
 * Also normalizes import_job table default charset from utf8mb4 to utf8mb3.
 */
class Version20260313160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix database collation: ensure customer_oauth_config and import_job use utf8mb3_unicode_ci';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->fixCustomerOauthConfig();
        $this->fixImportJob();
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Charset conversion is not reversed — the utf8mb3 collation is the correct state.
    }

    /**
     * @throws Exception
     */
    private function fixCustomerOauthConfig(): void
    {
        $tableExists = $this->tableExists('customer_oauth_config');

        if (!$tableExists) {
            $this->createCustomerOauthConfigFromScratch();

            return;
        }

        $collation = $this->getTableCollation('customer_oauth_config');

        if (null !== $collation && !str_starts_with($collation, 'utf8mb3')) {
            $this->addSql('ALTER TABLE customer_oauth_config CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci');
        }
    }

    /**
     * Creates the table with all columns from the original + subsequent migrations,
     * then marks those migrations as executed so they won't attempt to run again.
     *
     * @throws Exception
     */
    private function createCustomerOauthConfigFromScratch(): void
    {
        // Full table definition including columns from:
        // - Version20260227134517 (initial create)
        // - Version20260310120000 (widen secret to VARCHAR(512))
        // - Version20260312150000 (add default_organisation_id)
        $this->addSql('CREATE TABLE customer_oauth_config (
            id CHAR(36) NOT NULL,
            customer_id CHAR(36) NOT NULL,
            keycloak_client_id VARCHAR(255) NOT NULL,
            keycloak_client_secret VARCHAR(512) NOT NULL,
            keycloak_auth_server_url VARCHAR(500) NOT NULL,
            keycloak_realm VARCHAR(255) NOT NULL,
            keycloak_logout_route VARCHAR(1000) DEFAULT NULL,
            default_organisation_id CHAR(36) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_customer (customer_id),
            CONSTRAINT fk_customer_oauth_config_customer FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE,
            CONSTRAINT FK_customer_oauth_default_org FOREIGN KEY (default_organisation_id) REFERENCES orga (_o_id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci ENGINE = InnoDB');

        // Mark the three prior migrations as executed to prevent them from running
        // on this (already complete) table.
        $migrations = [
            'Application\\Migrations\\Version20260227134517',
            'Application\\Migrations\\Version20260310120000',
            'Application\\Migrations\\Version20260312150000',
        ];

        foreach ($migrations as $version) {
            $alreadyRecorded = $this->connection->fetchOne(
                'SELECT 1 FROM migration_versions WHERE version = :version',
                ['version' => $version]
            );

            if (false === $alreadyRecorded) {
                $this->addSql(
                    'INSERT INTO migration_versions (version, executed_at, execution_time) VALUES (:version, NOW(), 0)',
                    ['version' => $version]
                );
            }
        }
    }

    private function fixImportJob(): void
    {
        if (!$this->tableExists('import_job')) {
            return;
        }

        $collation = $this->getTableCollation('import_job');

        if (null !== $collation && !str_starts_with($collation, 'utf8mb3')) {
            $this->addSql('ALTER TABLE import_job CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci');
        }
    }

    /**
     * @throws Exception
     */
    private function tableExists(string $tableName): bool
    {
        $dbName = $this->connection->getDatabase();
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table',
            ['db' => $dbName, 'table' => $tableName]
        );

        return (int) $result > 0;
    }

    /**
     * @throws Exception
     */
    private function getTableCollation(string $tableName): ?string
    {
        $dbName = $this->connection->getDatabase();

        $result = $this->connection->fetchOne(
            'SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table',
            ['db' => $dbName, 'table' => $tableName]
        );

        return false !== $result ? (string) $result : null;
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
