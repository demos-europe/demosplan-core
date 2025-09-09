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

class Version20221116114633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T29692 & T29694: Allow determining if this user is identified by external provider';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _user ADD provided_by_identity_provider TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Determines if this user is identified by external provider\'');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _user DROP provided_by_identity_provider');
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
