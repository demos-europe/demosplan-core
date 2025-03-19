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

class Version20240716115746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ref DPALN-163 create new flag';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $columnExists = $this->connection->fetchOne("SHOW COLUMNS FROM workflow_place LIKE 'solved'");

        // check for column existence because down migration was added later
        if (false === $columnExists) {
            $this->addSql('ALTER TABLE workflow_place ADD solved TINYINT(1) DEFAULT FALSE');
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE workflow_place DROP COLUMN solved');
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
