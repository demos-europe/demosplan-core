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

class Version20260522073104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete orphan procedure_phase rows left behind by previous runs of dplan:procedure:delete.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            'DELETE pp FROM procedure_phase pp '
            .'LEFT JOIN _procedure p1 ON p1.phase_id = pp.id '
            .'LEFT JOIN _procedure p2 ON p2.public_participation_phase_id = pp.id '
            .'WHERE p1._p_id IS NULL AND p2._p_id IS NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        // Irreversible data cleanup.
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
