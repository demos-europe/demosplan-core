<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20241122105514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T: add new property topicalTag to _tag. This property is used to mark tags with a flag.
        This flag marks Tags that can be used by PI.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();


        $this->addSql('ALTER TABLE _tag ADD topical_tag TINYINT(1) DEFAULT 0 NOT NULL');

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();


        $this->addSql('ALTER TABLE _tag DROP topical_tag');

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
