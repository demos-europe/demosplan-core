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

class Version20250217163902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-15001: Add index on _files._f_hash';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $table = $schema->getTable('_files');
        if (!$table->hasIndex('IDX_8C0DACC5DCE34FD9')) {
            $table->addIndex(['_f_hash'], 'IDX_8C0DACC5DCE34FD9');
        }
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $table = $schema->getTable('_files');
        if ($table->hasIndex('IDX_8C0DACC5DCE34FD9')) {
            $table->dropIndex('IDX_8C0DACC5DCE34FD9');
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
