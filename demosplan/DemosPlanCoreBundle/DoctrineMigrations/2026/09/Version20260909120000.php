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
 * Adds {@see \demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure::$readOnly}, which marks a
 * procedure as archived/read-only: it stays reachable for reference, but all write permissions
 * inside it are replaced by a read-only set (Permissions::setProcedurePermissions()).
 */
final class Version20260909120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'feat (refs DPLAN-18375): Add read_only flag to _procedure';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure ADD read_only TINYINT(1) DEFAULT 0 NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure DROP read_only');
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
