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
 * The location reference (Ortsbezug) is stored separately from the drawing (polygon).
 */
final class Version20261109160848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'feat: (refs: DPLAN-18507) Add location reference columns to statements and draft statements';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _draft_statement ADD _ds_location_reference MEDIUMTEXT NOT NULL');
        $this->addSql('ALTER TABLE _statement ADD _st_location_reference TEXT NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _draft_statement DROP _ds_location_reference');
        $this->addSql('ALTER TABLE _statement DROP _st_location_reference');
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
