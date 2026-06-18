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
 * Increase sessions.sess_data column size from MEDIUMBLOB (16MB) to LONGBLOB (4GB).
 *
 * This prevents "Data too long for column 'sess_data'" errors when large amounts of data
 * are stored in the session (e.g., flash messages during complex operations like procedure creation).
 */
class Version20251125133751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase sessions.sess_data column size from MEDIUMBLOB (16MB) to LONGBLOB (4GB)';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE sessions MODIFY sess_data LONGBLOB NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Revert to original MEDIUMBLOB size
        // WARNING: This may fail if any session data exceeds 16MB
        $this->addSql('ALTER TABLE sessions MODIFY sess_data MEDIUMBLOB NOT NULL');
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
