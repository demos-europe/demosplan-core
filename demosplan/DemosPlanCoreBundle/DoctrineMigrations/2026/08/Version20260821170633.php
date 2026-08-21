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
 * Adds the per-customer {@see \demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer::$showlist}
 * column and backfills it from the orga-level {@see \demosplan\DemosPlanCoreBundle\Entity\User\Orga::$showlist}
 * column, so the public-agency visibility flag becomes per customer/orgaType instead of a single
 * value shared across all customers.
 */
final class Version20260821170633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-customer showlist column to relation_customer_orga_orga_type and backfill from _orga._o_showlist';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE relation_customer_orga_orga_type ADD showlist TINYINT(1) DEFAULT 1 NOT NULL');

        // Copy the orga-level showlist value into every per-customer status row of that orga.
        // Runs exactly once via this migration; never re-run manually (would overwrite diverged values).
        $this->addSql(<<<'SQL'
            UPDATE relation_customer_orga_orga_type osc
            JOIN _orga o ON osc._o_id = o._o_id
            SET osc.showlist = o._o_showlist
        SQL);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE relation_customer_orga_orga_type DROP showlist');
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
