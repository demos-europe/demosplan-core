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

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240220142305 extends AbstractMigration
{
    private const BASEMAP_GIS_URL = 'https://sgx.geodatenzentrum.de/wms_basemapde%';
    private const BASEMAP_GIS_NAME = 'basemap';

    public function getDescription(): string
    {
        return 'refs T17533: Update _g_print setting for gislayer (which use basemap) of only default masterblueprints.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        // Update gis layers where its procedure is a master template
        $this->addSql(
            'UPDATE _gis as g
        INNER JOIN _procedure AS p ON g._p_id = p._p_id
        SET g._g_print = 1, g.default_visibility = 1, g.enabled = 1
        WHERE g._g_url like :basemapGisUrl
        AND g._g_name = :basemapGisName
        AND p.master_template = 1',
            [
                'basemapGisUrl'  => self::BASEMAP_GIS_URL,
                'basemapGisName' => self::BASEMAP_GIS_NAME,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // This migration is not reversible because:
        // we can't be sure the _g_print flag was already set to 1
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
