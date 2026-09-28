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

class Version20260601060448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-17455: widen _procedure.extern_id to VARCHAR(255) to fit XBeteiligung planIDs';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure CHANGE extern_id extern_id VARCHAR(255) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Intentionally left empty. Narrowing extern_id back to VARCHAR(50) would
        // either abort (strict mode) or silently truncate (non-strict mode) any
        // planID longer than 50 characters that this migration was introduced to
        // support. A lossy revert is worse than no revert, so the column stays wide.
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
