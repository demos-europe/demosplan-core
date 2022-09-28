<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20220928110438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T28987: Create basic tables to allow tagging of institutions by institutions.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE orga_orga_institution_tag (orga__o_id CHAR(36) NOT NULL, orga_institution_tag_id CHAR(36) NOT NULL, INDEX IDX_F903E7DE57022B64 (orga__o_id), INDEX IDX_F903E7DED3164222 (orga_institution_tag_id), PRIMARY KEY(orga__o_id, orga_institution_tag_id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE orga_institution_tag (id CHAR(36) NOT NULL, _o_id CHAR(36) NOT NULL, label VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, INDEX IDX_5F64F7EA86245470 (_o_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE orga_orga_institution_tag ADD CONSTRAINT FK_F903E7DE57022B64 FOREIGN KEY (orga__o_id) REFERENCES _orga (_o_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orga_orga_institution_tag ADD CONSTRAINT FK_F903E7DED3164222 FOREIGN KEY (orga_institution_tag_id) REFERENCES orga_institution_tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orga_institution_tag ADD CONSTRAINT FK_5F64F7EA86245470 FOREIGN KEY (_o_id) REFERENCES _orga (_o_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE orga_orga_institution_tag DROP FOREIGN KEY FK_F903E7DED3164222');
        $this->addSql('DROP TABLE orga_orga_institution_tag');
        $this->addSql('DROP TABLE orga_institution_tag');
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
