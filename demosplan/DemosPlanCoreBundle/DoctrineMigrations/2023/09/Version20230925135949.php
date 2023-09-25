<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230925135949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T32796: fix faulty unique index on procedure_message.procedure_id';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE procedure_message DROP FOREIGN KEY FK_E7F5DA961624BCD2');
        $this->addSql('ALTER TABLE procedure_message DROP INDEX UNIQ_E7F5DA961624BCD2');
        $this->addSql('ALTER TABLE procedure_message ADD CONSTRAINT FK_E7F5DA961624BCD2 FOREIGN KEY (procedure_id) REFERENCES _procedure (_p_id)');
    }


    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

            $this->addSql('ALTER TABLE procedure_message ADD CONSTRAINT FK_E7F5DA961624BCD2 UNIQUE INDEX UNIQ_E7F5DA961624BCD2 (procedure_id)');
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
