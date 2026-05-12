<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260512125436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-17780: Add custom fields to Organisation Entity';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _orga ADD custom_fields JSON DEFAULT NULL COMMENT \'(DC2Type:dplan.custom_fields_value)\', CHANGE _o_gw_id _o_gw_id VARCHAR(250) DEFAULT NULL, CHANGE imprint imprint TEXT NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _orga DROP custom_fields, CHANGE _o_gw_id _o_gw_id VARCHAR(256) DEFAULT NULL, CHANGE imprint imprint MEDIUMTEXT NOT NULL');
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
