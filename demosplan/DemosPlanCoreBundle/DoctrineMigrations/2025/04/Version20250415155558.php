<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250415155558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-15338: Create custom field configuration table ';
    }

    /**
     * @throws Exception
     */
     public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('
            CREATE TABLE custom_field_configuration (
                id CHAR(36) NOT NULL,
                source_entity_id VARCHAR(36) NOT NULL,
                source_entity_class VARCHAR(255) NOT NULL,
                target_entity_class VARCHAR(255) NOT NULL,
                configuration JSON DEFAULT NULL COMMENT \'(DC2Type:dplan.custom_field_configuration)\',
                create_date DATETIME NOT NULL,
                modify_date DATETIME NOT NULL,
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB
        ');
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
