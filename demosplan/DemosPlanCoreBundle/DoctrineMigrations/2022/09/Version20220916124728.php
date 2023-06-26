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
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20220916124728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rollup database migrations';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('DELETE FROM migration_versions WHERE version NOT LIKE "%20200106150455" AND version NOT LIKE "%20220917124728"');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // down migration is not possible
        $this->abortIfNotMysql();
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
