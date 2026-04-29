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

class Version20260428120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pii_log table backing PiiAwareLogger: full PII-bearing log records '
            .'are persisted here while only a UUID + content hash + non-PII context are emitted '
            .'to the filesystem log.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            CREATE TABLE pii_log (
                id CHAR(36) NOT NULL PRIMARY KEY,
                created DATETIME NOT NULL,
                level SMALLINT NOT NULL,
                level_name VARCHAR(16) NOT NULL,
                channel VARCHAR(64) NOT NULL,
                message LONGTEXT NOT NULL,
                pii_context LONGTEXT NULL,
                non_pii_context LONGTEXT NULL,
                content_hash CHAR(64) NOT NULL,
                request_id VARCHAR(32) NULL,
                procedure_id CHAR(36) NULL,
                orga_id CHAR(36) NULL,
                source_context VARCHAR(8) NOT NULL,

                INDEX idx_pii_created (created),
                INDEX idx_pii_hash (content_hash),
                INDEX idx_pii_procedure (procedure_id),
                INDEX idx_pii_orga (orga_id),
                INDEX idx_pii_request (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE pii_log');
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
