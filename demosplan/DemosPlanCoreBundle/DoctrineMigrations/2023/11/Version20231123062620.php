<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231123062620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T: ';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE support_contact DROP FOREIGN KEY FK_8C8C0928B08E074E');

        $this->addSql('DROP INDEX IDX_8C8C0928B08E074E ON support_contact');

        $this->addSql('ALTER TABLE support_contact ADD e_mail_address VARCHAR(254) NOT NULL, DROP email_address');

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE support_contact ADD email_address CHAR(36) NOT NULL , DROP e_mail_address');

        $this->addSql('CREATE INDEX IDX_8C8C0928B08E074E ON support_contact (email_address)');

        $this->addSql('ALTER TABLE support_contact ADD CONSTRAINT FK_8C8C0928B08E074E FOREIGN KEY (email_address) REFERENCES email_address (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE support_contact CHANGE email_address email_address CHAR(36)');

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
