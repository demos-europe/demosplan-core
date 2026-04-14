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
 * Drops the cluster_statement boolean column from _statement.
 *
 * Cluster heads are now identified by entity_type = 'StatementGroup' (introduced in
 * Version20251209000000). The boolean flag is no longer needed.
 */
class Version20260413120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the cluster_statement column from _statement (superseded by entity_type discriminator)';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _statement DROP COLUMN cluster_statement');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _statement ADD COLUMN cluster_statement TINYINT(1) NOT NULL DEFAULT 0');

        $this->addSql("
            UPDATE _statement
            SET cluster_statement = 1
            WHERE entity_type = 'StatementGroup'
        ");
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
