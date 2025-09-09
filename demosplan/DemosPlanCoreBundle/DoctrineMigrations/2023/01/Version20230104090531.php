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

class Version20230104090531 extends AbstractMigration
{
    private const NEW_GIS_NAME = 'basemap';
    private const NEW_GIS_LAYER = 'de_basemapde_web_raster_farbe';
    private const NEW_GIS_URL = 'https://sgx.geodatenzentrum.de/wms_basemapde';
    private const NEW_COPYRIGHT_TEXT = '© basemap.de BKG (www.basemap.de) / LVermGeo SH (www.LVermGeoSH.schleswig-holstein.de)';

    private const OLD_GIS_NAME = 'Web-Atlas';
    private const OLD_GIS_LAYER = 'webatlasde';
    private const OLD_GIS_URL = 'https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6';
    private const OLD_COPYRIGHT_TEXT = 'Kartengrundlage: © GeoBasis-DE/LVermGeo SH (www.LVermGeoSH.schleswig-holstein.de)';

    public function getDescription(): string
    {
        return 'refs T29639: Replace the "Web-Atlas" by basemap as standard and sets a new copyright,
        only for procedure templates.';
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
            WHERE g._g_url = :oldGisUrl AND p._p_master = 1 AND p._p_deleted = 0',
            [
                'gisName'     => self::NEW_GIS_NAME,
                'gisLayer'    => self::NEW_GIS_LAYER,
                'newGisUrl'   => self::NEW_GIS_URL,
                'oldGisUrl'   => self::OLD_GIS_URL,
            ]
        );

        // Update the customers.
        $this->addSql(
            'UPDATE customer AS c
                SET c.base_layer_url = :newGisUrl,
                    c.base_layer_layers = :newGisLayer',
            [
                'newGisUrl'   => self::NEW_GIS_URL,
                'newGisLayer' => self::NEW_GIS_LAYER,
            ]
        );

        // Replace the old copyright entries by the new ones.
        $this->addSql(
            'UPDATE _procedure_settings AS ps
            INNER JOIN _procedure AS p ON ps._p_id = p._p_id
            SET ps.copyright = :newCopyrightText
            WHERE ps.copyright = :oldCopyrightText AND p._p_master = 1 AND p._p_deleted = 0',
            [
                'newCopyrightText' => self::NEW_COPYRIGHT_TEXT,
                'oldCopyrightText' => self::OLD_COPYRIGHT_TEXT,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // This migration is not reversible because we can't
        // be sure the Web-Atlas or the old copyright was already replaced by basemap or the new copyright.
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
