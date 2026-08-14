<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260814124203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add procedure_integration_token for cross-instance write access to a single procedure';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE procedure_integration_token (id CHAR(36) NOT NULL, customer CHAR(36) NOT NULL, revoked_by CHAR(36) DEFAULT NULL, `procedure` CHAR(36) NOT NULL, created_by CHAR(36) DEFAULT NULL, name VARCHAR(120) NOT NULL, token_prefix VARCHAR(12) NOT NULL, token_hash VARCHAR(255) NOT NULL, scopes JSON NOT NULL COMMENT \'(DC2Type:json)\', expires_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_CE52E07F81398E09 (customer), INDEX IDX_CE52E07F8E5493E3 (revoked_by), INDEX IDX_CE52E07F9C3CBC1F (`procedure`), INDEX IDX_CE52E07FDE12AB56 (created_by), UNIQUE INDEX procedure_integration_token_prefix_unique (token_prefix), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE procedure_integration_token ADD CONSTRAINT FK_CE52E07F81398E09 FOREIGN KEY (customer) REFERENCES customer (_c_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE procedure_integration_token ADD CONSTRAINT FK_CE52E07F8E5493E3 FOREIGN KEY (revoked_by) REFERENCES _user (_u_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE procedure_integration_token ADD CONSTRAINT FK_CE52E07F9C3CBC1F FOREIGN KEY (`procedure`) REFERENCES _procedure (_p_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE procedure_integration_token ADD CONSTRAINT FK_CE52E07FDE12AB56 FOREIGN KEY (created_by) REFERENCES _user (_u_id) ON DELETE SET NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE procedure_integration_token DROP FOREIGN KEY FK_CE52E07F81398E09');
        $this->addSql('ALTER TABLE procedure_integration_token DROP FOREIGN KEY FK_CE52E07F8E5493E3');
        $this->addSql('ALTER TABLE procedure_integration_token DROP FOREIGN KEY FK_CE52E07F9C3CBC1F');
        $this->addSql('ALTER TABLE procedure_integration_token DROP FOREIGN KEY FK_CE52E07FDE12AB56');
        $this->addSql('DROP TABLE procedure_integration_token');
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
