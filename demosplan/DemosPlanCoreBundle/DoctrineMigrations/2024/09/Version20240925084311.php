<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240925084311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-11379: Add customer id column to sort so that global news are sorted per customer';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();


        $this->addSql('DELETE FROM _manual_list_sort WHERE _p_id = :_p_id AND _mls_context = :_mls_context AND _mls_namespace = :_mls_namespace',
            [
            '_p_id' => 'global',
            '_mls_context' => 'global:news',
            '_mls_namespace' => 'content:news'
        ]);

        $this->addSql('ALTER TABLE _manual_list_sort ADD customer_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE _manual_list_sort ADD CONSTRAINT FK_3DECBA329395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_3DECBA329395C3F3 ON _manual_list_sort (customer_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _manual_list_sort DROP FOREIGN KEY FK_3DECBA329395C3F3');
        $this->addSql('DROP INDEX IDX_3DECBA329395C3F3 ON _manual_list_sort');
        $this->addSql('ALTER TABLE _manual_list_sort DROP customer_id');
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
