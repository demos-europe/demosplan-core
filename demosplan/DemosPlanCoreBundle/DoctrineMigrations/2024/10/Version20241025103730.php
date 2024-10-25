<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20241025103730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T:BEAA2-10 add new map related properties to the procedureSettings';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure_settings ADD _ps_pictogram_copyright VARCHAR(512) DEFAULT \'\' NOT NULL, ADD _ps_pictogram_alt_text VARCHAR(512) DEFAULT \'\' NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure_settings DROP _ps_pictogram_copyright, DROP _ps_pictogram_alt_text');
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySqlPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
