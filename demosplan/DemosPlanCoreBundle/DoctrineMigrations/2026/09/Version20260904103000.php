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
 * Adds {@see \demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer::$customer}, which binds a global
 * GIS layer to the customer it was created in and thereby limits which procedures it is copied into.
 *
 * Existing rows keep customer_id NULL: layers created before customer scoping stay visible to every
 * customer so they can still be administered and removed.
 */
final class Version20260904103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'chore: (refs: DPLAN-18357) Add customer_id to _gis to scope global GIS layers to a single customer';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _gis ADD customer_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE _gis ADD CONSTRAINT FK_8281ED259395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id)');
        $this->addSql('CREATE INDEX IDX_8281ED259395C3F3 ON _gis (customer_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _gis DROP FOREIGN KEY FK_8281ED259395C3F3');
        $this->addSql('DROP INDEX IDX_8281ED259395C3F3 ON _gis');
        $this->addSql('ALTER TABLE _gis DROP customer_id');
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
