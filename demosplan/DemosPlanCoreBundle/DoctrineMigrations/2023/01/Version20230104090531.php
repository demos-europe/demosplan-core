<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230104090531 extends AbstractMigration
{
    private const NEW_G_NAME = 'basemap';
    private const NEW_G_LAYER = 'de_basemapde_web_raster_farbe';
    private const NEW_G_URL = 'https://sgx.geodatenzentrum.de/wms_basemapde';
    //private const G_PROJECTION_LABEL = 'EPSG:3857';
    //private const G_PROJECTION_VALUE = '+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext +no_defs';
    private const NEW_COPYRIGHT_TEXT = '© basemap.de BKG (www.basemap.de) / LVermGeo SH (www.LVermGeoSH.schleswig-holstein.de)';

    private const OLD_G_NAME = 'Web-Atlas';
    private const OLD_G_LAYER = 'webatlasde';
    private const OLD_G_URL = 'https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6';
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
            SET g._g_name           = :gname,
                g._g_layers         = :glayer,
                g._g_url            = :newgurl
            WHERE g._g_url = :oldgurl AND p._p_master = 1 AND p._p_deleted = 0',
            [
                'gname'     => self::NEW_G_NAME,
                'glayer'    => self::NEW_G_LAYER,
                'newgurl'   => self::NEW_G_URL,
                'oldgurl'   => self::OLD_G_URL,
            ]
        );
        // Update the customers.
        $this->addSql(
            'UPDATE customer AS c
                SET c.base_layer_url = :newurl,
                    c.base_layer_layers = :newlayer',
            [
                'newurl' => self::NEW_G_URL,
                'newlayer' => self::NEW_G_LAYER,
            ]
        );
        // Replace the old copyright entries by the new ones.
        $this->addSql(
        'UPDATE _procedure_settings AS ps
            INNER JOIN _procedure AS p ON ps._p_id = p._p_id
            SET ps.copyright = :newcopyright
            WHERE ps.copyright = :oldcopyright AND p._p_master = 1 AND p._p_deleted = 0',
            [
                'newcopyright' => self::NEW_COPYRIGHT_TEXT,
                'oldcopyright' => self::OLD_COPYRIGHT_TEXT,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Replace all basemap Entries by Web-Atlas (only for procedure templates).
        $this->addSql(
            'UPDATE _gis AS g
            INNER JOIN _procedure AS p ON g._p_id = p._p_id
            SET g._g_name           = :oldgname,
                g._g_layers         = :oldglayer,
                g._g_url            = :oldgurl
            WHERE g._g_url = :newgurl AND p._p_master = 1 AND p._p_deleted = 0',
            [
                'oldgname'  => self::OLD_G_NAME,
                'oldglayer' => self::OLD_G_LAYER,
                'oldgurl'   => self::OLD_G_URL,
                'newgurl'   => self::NEW_G_URL,
            ]
        );

        // Set basemap back to Web-Atlas for the customers.
        $this->addSql(
            'UPDATE customer AS c
                SET c.base_layer_url = :oldurl,
                    c.base_layer_layers = :oldlayer',
            [
                'oldurl' => self::OLD_G_URL,
                'oldlayer' => self::OLD_G_LAYER,
            ]
        );

        // Replace the new copyright entries by the old ones.
        $this->addSql(
            'UPDATE _procedure_settings AS ps
            INNER JOIN _procedure AS p ON ps._p_id = p._p_id
            SET ps.copyright = :oldcopyright
            WHERE ps.copyright = :newcopyright AND p._p_master = 1 AND p._p_deleted = 0',
            [
                'newcopyright' => self::NEW_COPYRIGHT_TEXT,
                'oldcopyright' => self::OLD_COPYRIGHT_TEXT,
            ]
        );
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
