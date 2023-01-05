<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230104090531 extends AbstractMigration
{
    private const OLD_COPYRIGHT_TEXT = 'Kartengrundlage: © GeoBasis-DE/LVermGeo SH (www.LVermGeoSH.schleswig-holstein.de)';
    private const NEW_COPYRIGHT_TEXT = '© basemap.de BKG (www.basemap.de) / LVermGeo SH (www.LVermGeoSH.schleswig-holstein.de)';
    private const OLD_G_NAME1 = 'Web-Atlas';
    private const OLD_G_NAME2 = 'WebAtlas';
    private const NEW_G_NAME = 'basemap';
    private const G_URL = 'https://sgx.geodatenzentrum.de/wms_basemapde';
    private const G_LAYER = 'de_basemapde_web_raster_farbe';
    private const G_PROJECTION_LABEL = 'EPSG:3857';
    private const G_PROJECTION_VALUE = '+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext +no_defs';
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
                g._g_url            = :gurl,
                g._projection_label = :gplabel,
                g._projection_value = :gpvalue
            WHERE (g._g_name = :oldgname1 OR g._g_name = :oldgname2) AND p._p_master = 1',
            [
                'gname'     => self::NEW_G_NAME,
                'oldgname1' => self::OLD_G_NAME1,
                'oldgname2' => self::OLD_G_NAME2,
                'glayer'    => self::G_LAYER,
                'gurl'      => self::G_URL,
                'gplabel'   => self::G_PROJECTION_LABEL,
                'gpvalue'   => self::G_PROJECTION_VALUE,
            ]
        );
        // Replace the old copyright entries by the new ones.
        $this->addSql(
        'UPDATE _procedure_settings AS ps
            INNER JOIN _procedure AS p ON ps._p_id = p._p_id
            SET ps.copyright = :newcopyright
            WHERE ps.copyright = :oldcopyright AND p._p_master = 1',
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
