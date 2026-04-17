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

class Version20260414120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create personal_data_audit_log table for GDPR-compliant audit trail of personal data changes';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            CREATE TABLE personal_data_audit_log (
                id CHAR(36) NOT NULL PRIMARY KEY,
                user_id CHAR(36) NULL,
                user_name VARCHAR(255) NULL,
                entity_type VARCHAR(255) NOT NULL,
                entity_id CHAR(36) NOT NULL,
                entity_field VARCHAR(255) NOT NULL,
                change_type VARCHAR(20) NOT NULL,
                pre_update_value LONGTEXT NULL,
                post_update_value LONGTEXT NULL,
                is_sensitive_field TINYINT(1) NOT NULL DEFAULT 0,
                procedure_id CHAR(36) NULL,
                orga_id CHAR(36) NULL,
                context VARCHAR(20) NULL,
                created DATETIME NOT NULL,

                INDEX idx_pda_entity (entity_type, entity_id),
                INDEX idx_pda_user (user_id),
                INDEX idx_pda_created (created),
                INDEX idx_pda_procedure (procedure_id),
                INDEX idx_pda_orga (orga_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE personal_data_audit_log');
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
