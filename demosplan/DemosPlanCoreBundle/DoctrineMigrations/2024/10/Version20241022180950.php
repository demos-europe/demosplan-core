<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20241022180950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-12711 : adjust statement intern id length';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();


        $this->addSql('ALTER TABLE _statement CHANGE _st_intern_id _st_intern_id CHAR(255)');

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();


        $this->addSql('ALTER TABLE _statement CHANGE _st_intern_id _st_intern_id CHAR(35)');

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
