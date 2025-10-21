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

class Version20251021105355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make tag and tag topic title unique constraints case-sensitive by changing collation to utf8_bin';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Change collation of _t_title column to utf8_bin for case-sensitive comparison
        $this->addSql('ALTER TABLE _tag MODIFY _t_title VARCHAR(255) COLLATE utf8_bin NOT NULL');

        // Change collation of _tt_title column to utf8_bin for case-sensitive comparison
        $this->addSql('ALTER TABLE _tag_topic MODIFY _tt_title VARCHAR(255) COLLATE utf8_bin NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Revert back to case-insensitive utf8_unicode_ci collation
        $this->addSql('ALTER TABLE _tag MODIFY _t_title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL');
        $this->addSql('ALTER TABLE _tag_topic MODIFY _tt_title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL');
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
