<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240821091730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN12306: Territory should be empty string or json format';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('UPDATE _procedure_settings SET _ps_territory = "{}" WHERE _ps_territory = "[]"');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // This Migration doesn't need any down method.
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
