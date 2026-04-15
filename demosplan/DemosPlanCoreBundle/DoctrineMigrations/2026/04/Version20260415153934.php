<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260415153934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16766: Add nullable phase_definition_id FK column to _draft_statement_versions';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _draft_statement_versions ADD phase_definition_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE _draft_statement_versions ADD CONSTRAINT FK_1C6084CF8EFDFE33 FOREIGN KEY (phase_definition_id) REFERENCES procedure_phase_definition (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_1C6084CF8EFDFE33 ON _draft_statement_versions (phase_definition_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _draft_statement_versions DROP FOREIGN KEY FK_1C6084CF8EFDFE33');
        $this->addSql('DROP INDEX IDX_1C6084CF8EFDFE33 ON _draft_statement_versions');
        $this->addSql('ALTER TABLE _draft_statement_versions DROP phase_definition_id');
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
