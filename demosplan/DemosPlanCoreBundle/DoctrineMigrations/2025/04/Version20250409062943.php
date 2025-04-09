<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250409062943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-15372: set gislayer visibility_group_id to null if empty';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql("UPDATE _gis SET visibility_group_id = NULL
                        WHERE visibility_group_id = ''",
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // down migration is not possible
        $this->abortIfNotMysql();
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
