<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231110171242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete migrations that where moved to addons';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DELETE FROM migration_versions WHERE version = :version', ['version' => 'Application\Migrations\Version20230706091408']);
        $this->addSql('DELETE FROM migration_versions WHERE version = :version', ['version' => 'Application\Migrations\Version20230711125038']);
        $this->addSql('DELETE FROM migration_versions WHERE version = :version', ['version' => 'on']);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        // down migrate not possible
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
