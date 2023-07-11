<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230711125038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs 32796 create ProcedureMessage in relation with XBeteiligung ';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE procedure_message DROP FOREIGN KEY FK_E7F5DA961624BCD2');
        $table = $schema->getTable('procedure_message');
        $table->dropColumn('procedure_id');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
    }

    /**S
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
