<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231005081257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T33127: Adds a phase count to procedures and statements.
        Sets its default value for all preExisting Entities to 1';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure ADD p_phase_count INT NOT NULL');
        $this->addSql('ALTER TABLE _statement ADD st_phase_count INT NOT NULL');

        $this->addSql('UPDATE _procedure SET p_phase_count = 1');
        $this->addSql('UPDATE _statement SET st_phase_count = 1');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _statement DROP st_phase_count');
        $this->addSql('ALTER TABLE _procedure DROP p_phase_count');
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
