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

class Version20231005081257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T33127: Adds a phase count to procedures and statements.
        Sets its default value for all preExisting Entities to 1';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _statement ADD st_phase_count INT DEFAULT 1 NOT NULL ');
        $this->addSql('ALTER TABLE _procedure ADD p_phase_count INT DEFAULT 1 NOT NULL ');
        $this->addSql('ALTER TABLE _procedure ADD _p_public_participation_phase_count INT DEFAULT 1 NOT NULL ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _statement DROP st_phase_count');
        $this->addSql('ALTER TABLE _procedure DROP p_phase_count');
        $this->addSql('ALTER TABLE _procedure DROP _p_public_participation_phase_count');
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
