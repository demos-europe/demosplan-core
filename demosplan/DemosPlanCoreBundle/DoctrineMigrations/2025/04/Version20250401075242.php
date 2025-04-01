<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250401075242 extends AbstractMigration
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

            $this->addSql('CREATE TABLE custom_field_configuration (id CHAR(36) NOT NULL, template_entity_id VARCHAR(36) NOT NULL, template_entity_class VARCHAR(255) NOT NULL, configuration JSON DEFAULT NULL COMMENT \'(DC2Type:dplan.custom_fields_template)\', create_date DATETIME NOT NULL, modify_date DATETIME NOT NULL, value_entity_class VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('DROP TABLE custom_field_configuration');
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
