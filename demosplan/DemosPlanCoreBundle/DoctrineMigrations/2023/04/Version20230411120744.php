<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230411120744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T28005: Delete externIds of all child-statements in order to get these from related original statements.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _statement CHANGE _st_extern_id _st_extern_id CHAR(25) NULL');
        $this->addSql("UPDATE `_statement` SET `_st_extern_id` = NULL WHERE `_st_o_id` IS NOT NULL AND entity_type = 'Statement'");
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        //impossible
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
