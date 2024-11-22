<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20241122110226 extends AbstractMigration
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
        //Add Category ID to Institution Tag
        $this->addSql('ALTER TABLE institution_tag ADD category_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE institution_tag ADD CONSTRAINT FK_6C96B95C12469DE2 FOREIGN KEY (category_id) REFERENCES institution_tag_category (id)');

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE institution_tag DROP FOREIGN KEY FK_6C96B95C12469DE2');
        $this->addSql('DROP INDEX IDX_6C96B95C12469DE2 ON institution_tag');
        $this->addSql('ALTER TABLE institution_tag DROP category_id');
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
