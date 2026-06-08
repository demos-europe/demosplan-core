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

class Version20260429192007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs: DPLAN-17727: Create login_audit table to record successful and failed authentication attempts. '
            .'Stores user id, result, authenticator, user agent, session id hash and timestamp.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if ($schema->hasTable('login_audit')) {
            return;
        }

        // user_id is stored as a plain CHAR column without a foreign key by design:
        // audit rows must survive user deletion (compliance requirement) and we want
        // to keep the new table on utf8mb4 rather than match `_user._u_id`'s legacy
        // utf8mb3 charset. Lookups by user happen via the (user_id, created_date) index.
        $this->addSql(<<<'SQL'
            CREATE TABLE login_audit (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) DEFAULT NULL,
                result VARCHAR(16) NOT NULL,
                failure_reason VARCHAR(255) DEFAULT NULL,
                authenticator VARCHAR(191) NOT NULL,
                user_agent VARCHAR(512) DEFAULT NULL,
                session_id_hash CHAR(64) DEFAULT NULL,
                created_date DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX IDX_LOGIN_AUDIT_USER_DATE (user_id, created_date),
                INDEX IDX_LOGIN_AUDIT_DATE (created_date),
                INDEX IDX_LOGIN_AUDIT_SESSION_AUTH (session_id_hash, authenticator)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if (!$schema->hasTable('login_audit')) {
            return;
        }

        $this->addSql('DROP TABLE login_audit');
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
