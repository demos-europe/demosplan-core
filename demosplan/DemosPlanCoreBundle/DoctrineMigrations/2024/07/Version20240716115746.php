<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240716115746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ref DPALN-163 create new flag';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE workflow_place ADD solved TINYINT(1) DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        // no down migration possible.
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
