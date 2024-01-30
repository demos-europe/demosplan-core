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

class Version20240130051137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T36196: Enhance limit of chars for newly added phases.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure CHANGE _p_phase _p_phase VARCHAR(255) NOT NULL, CHANGE _p_public_participation_phase _p_public_participation_phase VARCHAR(255) NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure CHANGE _p_phase _p_phase VARCHAR(50) NOT NULL, CHANGE _p_public_participation_phase _p_public_participation_phase VARCHAR(20) NOT NULL');
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
