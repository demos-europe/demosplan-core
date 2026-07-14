<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260714195933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-18198: add default assignee relation to tag';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _tag ADD default_assignee_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE _tag ADD CONSTRAINT FK_4EE2AE798D1F6ED6 FOREIGN KEY (default_assignee_id) REFERENCES _user (_u_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4EE2AE798D1F6ED6 ON _tag (default_assignee_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _tag DROP FOREIGN KEY FK_4EE2AE798D1F6ED6');
        $this->addSql('DROP INDEX IDX_4EE2AE798D1F6ED6 ON _tag');
        $this->addSql('ALTER TABLE _tag DROP default_assignee_id');
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
