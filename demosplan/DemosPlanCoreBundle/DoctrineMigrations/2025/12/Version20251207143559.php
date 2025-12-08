<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20251207143559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add file_name column to import_job table';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            ALTER TABLE import_job
            ADD COLUMN file_name VARCHAR(255) NOT NULL DEFAULT "" AFTER file_path
        ');

        // Populate file_name from file_path basename for existing records
        $this->addSql('
            UPDATE import_job
            SET file_name = SUBSTRING_INDEX(file_path, "/", -1)
            WHERE file_name = ""
        ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE import_job DROP COLUMN file_name');
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
