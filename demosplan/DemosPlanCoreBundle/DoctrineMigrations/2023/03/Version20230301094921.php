<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230301094921 extends AbstractMigration
{
    private const NEW_GIS_NAME = 'basemap';
    private const NEW_GIS_LAYER = 'de_basemapde_web_raster_farbe';
    private const NEW_GIS_URL = 'https://sgx.geodatenzentrum.de/wms_basemapde';

    private const OLD_GIS_URL = '%webatlas%';

    public function getDescription(): string
    {
        return 'refs T31604: Replace every occurrences of "Web-Atlas" by basemap only for procedure templates.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Replace all Web-Atlas Entries by basemap (only for procedure templates).
        $this->addSql(
            'UPDATE _gis AS g
            INNER JOIN _procedure AS p ON g._p_id = p._p_id
            SET
                g._g_name = :gisName,
                g._g_layers = :gisLayer,
                g._g_url = :newGisUrl
            WHERE g._g_url LIKE :oldGisUrl AND p._p_master = 1 AND p._p_deleted = 0',
            [
                'gisName'     => self::NEW_GIS_NAME,
                'gisLayer'    => self::NEW_GIS_LAYER,
                'newGisUrl'   => self::NEW_GIS_URL,
                'oldGisUrl'   => self::OLD_GIS_URL,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // This migration is not reversible because we can't
        // be sure the Web-Atlas was already replaced by basemap.
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
