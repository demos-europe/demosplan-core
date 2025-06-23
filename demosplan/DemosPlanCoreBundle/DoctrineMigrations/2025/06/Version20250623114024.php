<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250623114024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-15078: Add flag to allow anonymous statements in a procedure.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql(<<<'SQL'
            ALTER TABLE _procedure_settings ADD allow_anonymous_atatements TINYINT(1) DEFAULT 1 NOT NULL
        SQL);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(<<<'SQL'
            ALTER TABLE _procedure_settings DROP allow_anonymous_atatements
        SQL);
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
