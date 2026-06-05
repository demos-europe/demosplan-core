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

class Version20260401120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill _o_id on manual statements created for institutions via the autofill dropdown';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Backfill _o_id for manual statements where:
        // 1. manual = 1 (created by a Fachplaner)
        // 2. _o_id IS NULL (org link missing — the bug)
        // 3. submitterRole = 'publicagency' in misc_data (institution was selected)
        // 4. _stm_orga_name is not empty (institution name was filled)
        // 5. The matched org is invited to the procedure (_procedure_orga_doctrine)
        // 6. Only one org with that name is invited (avoid ambiguous matches)
        $this->addSql(<<<'SQL'
            UPDATE _statement s
            JOIN _statement_meta sm ON sm._st_id = s._st_id
            JOIN (
                SELECT po._p_id, o._o_id, o._o_name
                FROM _procedure_orga_doctrine po
                JOIN _orga o ON o._o_id = po._o_id
            ) matched ON matched._p_id = s._p_id AND matched._o_name = sm._stm_orga_name
            SET s._o_id = matched._o_id
            WHERE s.manual = 1
              AND s._o_id IS NULL
              AND sm._stm_orga_name != ''
              AND sm._stm_misc_data LIKE '%submitterRole%publicagency%'
              AND (
                  SELECT COUNT(DISTINCT o2._o_id)
                  FROM _procedure_orga_doctrine po2
                  JOIN _orga o2 ON o2._o_id = po2._o_id
                  WHERE po2._p_id = s._p_id AND o2._o_name = sm._stm_orga_name
              ) = 1
        SQL);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->write('Down migration not supported — cannot distinguish backfilled rows from legitimate ones.');
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
