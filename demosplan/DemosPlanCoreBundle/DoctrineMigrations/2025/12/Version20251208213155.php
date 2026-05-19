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

class Version20251208213155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create import_job table for async Excel import processing';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql("
            CREATE TABLE import_job (
                id CHAR(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL PRIMARY KEY,
                procedure_id CHAR(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
                user_id CHAR(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NOT NULL DEFAULT '',
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                last_activity_at DATETIME NULL,
                result JSON NULL,
                error TEXT NULL,
                created_at DATETIME NOT NULL,

                INDEX idx_status (status),
                INDEX idx_procedure_user (procedure_id, user_id),

                CONSTRAINT fk_import_job_procedure
                    FOREIGN KEY (procedure_id) REFERENCES _procedure(_p_id) ON DELETE CASCADE,
                CONSTRAINT fk_import_job_user
                    FOREIGN KEY (user_id) REFERENCES _user(_u_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE import_job');
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
