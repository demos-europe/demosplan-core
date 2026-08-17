<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260814175722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add procedure_pairing_code, the single-use code exchanged for an integration token';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE procedure_pairing_code (id CHAR(36) NOT NULL, procedure_id CHAR(36) NOT NULL, customer_id CHAR(36) NOT NULL, created_by CHAR(36) DEFAULT NULL, code_hash VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, scopes JSON NOT NULL COMMENT \'(DC2Type:json)\', expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_91837A411624BCD2 (procedure_id), INDEX IDX_91837A419395C3F3 (customer_id), INDEX IDX_91837A41DE12AB56 (created_by), UNIQUE INDEX procedure_pairing_code_hash_unique (code_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE procedure_pairing_code ADD CONSTRAINT FK_91837A411624BCD2 FOREIGN KEY (procedure_id) REFERENCES _procedure (_p_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE procedure_pairing_code ADD CONSTRAINT FK_91837A419395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE procedure_pairing_code ADD CONSTRAINT FK_91837A41DE12AB56 FOREIGN KEY (created_by) REFERENCES _user (_u_id) ON DELETE SET NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE procedure_pairing_code DROP FOREIGN KEY FK_91837A411624BCD2');
        $this->addSql('ALTER TABLE procedure_pairing_code DROP FOREIGN KEY FK_91837A419395C3F3');
        $this->addSql('ALTER TABLE procedure_pairing_code DROP FOREIGN KEY FK_91837A41DE12AB56');
        $this->addSql('DROP TABLE procedure_pairing_code');
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
