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

class Version20250409185422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-15442: Readjustment of procedure-customer relationship in code seems to lead to this rename, without further impact.
        To keep the diff in sync, allow this renaming.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $procedureTable = $schema->getTable('_procedure');

        // Check if the old index exists before trying to rename it
        if ($procedureTable->hasIndex('fk_d1a01d0281398e09')) {
            $this->addSql('ALTER TABLE _procedure RENAME INDEX fk_d1a01d0281398e09 TO IDX_D1A01D0281398E09');
        }
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $procedureTable = $schema->getTable('_procedure');

        // Check if the new index exists before trying to rename it back
        if ($procedureTable->hasIndex('IDX_D1A01D0281398E09')) {
            $this->addSql('ALTER TABLE _procedure RENAME INDEX IDX_D1A01D0281398E09 TO fk_d1a01d0281398e09');
        }
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
