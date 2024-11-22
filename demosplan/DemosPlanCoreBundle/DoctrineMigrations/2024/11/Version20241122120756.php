<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20241122120756 extends AbstractMigration
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

        $this->addSql('ALTER TABLE institution_tag DROP FOREIGN KEY FK_6C96B95C56E11002');
        $this->addSql('DROP INDEX IDX_6C96B95C56E11002 ON institution_tag');
        $this->addSql('ALTER TABLE institution_tag DROP INDEX unique_label_for_orga');
        $this->addSql('ALTER TABLE institution_tag DROP COLUMN owning_organisation_id');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE institution_tag ADD owning_organisation_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE institution_tag ADD CONSTRAINT FK_6C96B95C56E11002 FOREIGN KEY (owning_organisation_id) REFERENCES _orga (_o_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_label_for_orga ON institution_tag (owning_organisation_id, `label`)');
        $this->addSql('CREATE INDEX IDX_6C96B95C56E11002 ON institution_tag (owning_organisation_id)');
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
