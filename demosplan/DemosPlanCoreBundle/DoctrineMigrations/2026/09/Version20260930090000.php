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
 * Users are wiped instead of deleted, so tags kept pointing at wiped users as default assignee.
 */
final class Version20260930090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'fix: (refs: DPLAN-18417) Clear default assignee of tags that reference a deleted user';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('UPDATE _tag t INNER JOIN _user u ON u._u_id = t.default_assignee_id SET t.default_assignee_id = NULL WHERE u._u_deleted = 1');
    }

    public function down(Schema $schema): void
    {
        // the cleared references cannot be restored
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
