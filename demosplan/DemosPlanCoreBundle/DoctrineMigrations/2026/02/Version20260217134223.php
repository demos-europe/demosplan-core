<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260217134223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_tokens table for KeyCloak token refresh and request buffering';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if table already exists
        if ($schema->hasTable('oauth_tokens')) {
            $this->write('Table oauth_tokens already exists, skipping creation');
            return;
        }

        $this->addSql('CREATE TABLE oauth_tokens (id INT AUTO_INCREMENT NOT NULL, user_id CHAR(36) NOT NULL, access_token LONGTEXT DEFAULT NULL, refresh_token LONGTEXT DEFAULT NULL, id_token LONGTEXT DEFAULT NULL, access_token_expires_at DATETIME DEFAULT NULL, refresh_token_expires_at DATETIME DEFAULT NULL, pending_page_url LONGTEXT DEFAULT NULL, pending_request_url LONGTEXT DEFAULT NULL, pending_request_method VARCHAR(10) DEFAULT NULL, pending_request_body LONGTEXT DEFAULT NULL, pending_request_content_type VARCHAR(100) DEFAULT NULL, pending_request_has_files TINYINT(1) DEFAULT 0 NOT NULL, pending_request_files_metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', pending_request_timestamp DATETIME DEFAULT NULL, provider VARCHAR(50) DEFAULT \'keycloak_ozg\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_access_expires (access_token_expires_at), INDEX idx_pending_timestamp (pending_request_timestamp), UNIQUE INDEX unique_user_id (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE oauth_tokens ADD CONSTRAINT FK_C06D3296A76ED395 FOREIGN KEY (user_id) REFERENCES _user (_u_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if table exists before dropping
        if (!$schema->hasTable('oauth_tokens')) {
            $this->write('Table oauth_tokens does not exist, skipping rollback');
            return;
        }

        $this->addSql('ALTER TABLE oauth_tokens DROP FOREIGN KEY FK_C06D3296A76ED395');
        $this->addSql('DROP TABLE oauth_tokens');
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
