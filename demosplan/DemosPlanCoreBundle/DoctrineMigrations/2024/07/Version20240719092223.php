<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240719092223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-12133: Add 2fa user fields';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _user ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD totp_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD auth_code VARCHAR(255) DEFAULT NULL, ADD auth_code_email_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _user DROP totp_secret, DROP totp_enabled, DROP auth_code, DROP auth_code_email_enabled');
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
