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
 * Backfill the entity_type discriminator on _statement so that cluster heads and
 * cluster members are distinguishable from plain statements:
 *   - cluster_statement = 1                 -> 'StatementGroup'
 *   - head_statement_id IS NOT NULL         -> 'StatementMember'
 *   - everything else keeps its current value ('Statement' / 'Segment').
 */
class Version20260527100811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill _statement.entity_type with StatementGroup / StatementMember for cluster heads and members';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            "UPDATE _statement SET entity_type = 'StatementGroup' WHERE cluster_statement = 1"
        );

        $this->addSql(
            "UPDATE _statement SET entity_type = 'StatementMember' WHERE head_statement_id IS NOT NULL"
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            "UPDATE _statement SET entity_type = 'Statement' WHERE entity_type IN ('StatementGroup', 'StatementMember')"
        );
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
