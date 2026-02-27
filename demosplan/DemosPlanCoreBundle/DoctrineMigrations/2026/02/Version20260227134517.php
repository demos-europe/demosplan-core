<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260227134517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs AD51641: Add customer_oauth_config table for per-customer Keycloak OAuth2 credentials';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE customer_oauth_config (
            id CHAR(36) NOT NULL,
            customer_id CHAR(36) NOT NULL,
            keycloak_client_id VARCHAR(255) NOT NULL,
            keycloak_client_secret VARCHAR(255) NOT NULL,
            keycloak_auth_server_url VARCHAR(500) NOT NULL,
            keycloak_realm VARCHAR(255) NOT NULL,
            keycloak_logout_route VARCHAR(1000) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_customer (customer_id),
            CONSTRAINT fk_customer_oauth_config_customer FOREIGN KEY (customer_id) REFERENCES customer (_c_id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE customer_oauth_config');
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
