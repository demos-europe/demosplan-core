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

class Version20240821091730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN12306: Territory should be empty string or json format';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('UPDATE _procedure_settings SET _ps_territory = :object WHERE _ps_territory = :array', ['object' => '{}', 'array' => '[]']);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // This Migration doesn't need any down method.
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
