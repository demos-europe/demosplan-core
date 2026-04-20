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
 * Create pending_permission table for queuing permissions during customer creation.
 *
 * This table stores permission "intents" that should be applied to organizations
 * when they are created. This solves the problem of enabling permissions during
 * customer creation when no organizations exist yet.
 */
class Version20251120100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pending_permission table for queuing permissions to be applied when organizations are created';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            CREATE TABLE pending_permission (
                _pp_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\',
                _pp_customer_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\',
                _pp_permission VARCHAR(255) NOT NULL,
                _pp_role_code VARCHAR(10) NOT NULL,
                _pp_orga_type VARCHAR(50) NOT NULL,
                _pp_created_at DATETIME NOT NULL,
                _pp_description TEXT DEFAULT NULL,
                _pp_auto_delete TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (_pp_id),
                INDEX idx_pp_customer (_pp_customer_id),
                INDEX idx_pp_orga_type (_pp_orga_type),
                INDEX idx_pp_customer_orga_type (_pp_customer_id, _pp_orga_type),
                CONSTRAINT FK_pending_permission_customer
                    FOREIGN KEY (_pp_customer_id)
                    REFERENCES customer (_c_id)
                    ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE IF EXISTS pending_permission');
    }

    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );
    }
}
