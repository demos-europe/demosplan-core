<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231121091548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T35528: Adjust unique constraints to not conflict with different support_contacts';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE support_contact DROP INDEX customer_title_unique');
        $this->addSql('ALTER TABLE support_contact ADD UNIQUE customer_title_type_unique(customer, title, `type`);');


    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE support_contact DROP INDEX customer_title_type_unique');
        $this->addSql('ALTER TABLE support_contact ADD UNIQUE customer_title_unique(customer, title);');


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
