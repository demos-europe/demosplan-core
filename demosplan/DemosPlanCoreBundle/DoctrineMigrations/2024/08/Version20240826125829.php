<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240826125829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adjust onDelete operation from restrict to cascade in order to delete _orga_addresses_doctrine regardless
        of parent restrictions.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _orga_addresses_doctrine DROP FOREIGN KEY FK_9DE5B2B386245470');
        $this->addSql('ALTER TABLE _orga_addresses_doctrine DROP FOREIGN KEY FK_9DE5B2B366FB2343');
        $this->addSql('ALTER TABLE _orga_addresses_doctrine ADD CONSTRAINT FK_9DE5B2B386245470 FOREIGN KEY (_o_id) REFERENCES _orga (_o_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE _orga_addresses_doctrine ADD CONSTRAINT FK_9DE5B2B366FB2343 FOREIGN KEY (_a_id) REFERENCES _address (_a_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE _orga_addresses_doctrine DROP FOREIGN KEY FK_9DE5B2B386245470');
        $this->addSql('ALTER TABLE _orga_addresses_doctrine DROP FOREIGN KEY FK_9DE5B2B366FB2343');
        $this->addSql('ALTER TABLE _orga_addresses_doctrine ADD CONSTRAINT FK_9DE5B2B386245470 FOREIGN KEY (_o_id) REFERENCES _orga (_o_id)');
        $this->addSql('ALTER TABLE _orga_addresses_doctrine ADD CONSTRAINT FK_9DE5B2B366FB2343 FOREIGN KEY (_a_id) REFERENCES _address (_a_id)');
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
