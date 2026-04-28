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
 * Introduces StatementGroup and StatementMember as explicit Doctrine entity types.
 *
 * Existing cluster head statements (cluster_statement = 1, head_statement_id IS NULL)
 * are migrated to entity_type = 'StatementGroup'.
 *
 * Existing cluster member statements (head_statement_id IS NOT NULL) are migrated
 * to entity_type = 'StatementMember'.
 *
 * No schema changes are required: the entity_type discriminator column, the name
 * column and the head_statement_id column already exist in the _statement table.
 */
class Version20251209000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate cluster head and member statements to StatementGroup and StatementMember entity types';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Cluster heads become StatementGroup
        $this->addSql("
            UPDATE _statement
            SET entity_type = 'StatementGroup'
            WHERE cluster_statement = 1
              AND head_statement_id IS NULL
              AND entity_type = 'Statement'
        ");

        // Cluster members become StatementMember
        $this->addSql("
            UPDATE _statement
            SET entity_type = 'StatementMember'
            WHERE head_statement_id IS NOT NULL
              AND entity_type = 'Statement'
        ");
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql("
            UPDATE _statement
            SET entity_type = 'Statement'
            WHERE entity_type IN ('StatementGroup', 'StatementMember')
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
