<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240829111757 extends AbstractMigration
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

        $this->addSql('CREATE TABLE statement_part (id CHAR(36) NOT NULL, statement_id CHAR(36) DEFAULT NULL, paragraph_id CHAR(36) DEFAULT NULL, document_id CHAR(36) DEFAULT NULL, element_id CHAR(36) DEFAULT NULL, assignee_id CHAR(36) DEFAULT NULL, extern_id CHAR(25) NOT NULL, status CHAR(50) NOT NULL, created DATETIME NOT NULL, text MEDIUMTEXT NOT NULL, recommendation MEDIUMTEXT NOT NULL, memo TEXT NOT NULL, reason_paragraph TEXT NOT NULL, planning_document VARCHAR(4096) NOT NULL, file CHAR(255) NOT NULL, polygon TEXT NOT NULL, replied TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_5B2F70EA849CB65B (statement_id), INDEX IDX_5B2F70EA8B50597F (paragraph_id), INDEX IDX_5B2F70EAC33F7837 (document_id), INDEX IDX_5B2F70EA1F1F2A24 (element_id), INDEX IDX_5B2F70EA59EC7D60 (assignee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE _statement_part_tag (id CHAR(36) NOT NULL, _t_id CHAR(36) NOT NULL, INDEX IDX_EDB290AABF396750 (id), INDEX IDX_EDB290AA13C84EE (_t_id), PRIMARY KEY(id, _t_id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE statement_part ADD CONSTRAINT FK_5B2F70EA849CB65B FOREIGN KEY (statement_id) REFERENCES _statement (_st_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE statement_part ADD CONSTRAINT FK_5B2F70EA8B50597F FOREIGN KEY (paragraph_id) REFERENCES _para_doc_version (_pdv_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE statement_part ADD CONSTRAINT FK_5B2F70EAC33F7837 FOREIGN KEY (document_id) REFERENCES _single_doc_version (_sdv_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE statement_part ADD CONSTRAINT FK_5B2F70EA1F1F2A24 FOREIGN KEY (element_id) REFERENCES _elements (_e_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE statement_part ADD CONSTRAINT FK_5B2F70EA59EC7D60 FOREIGN KEY (assignee_id) REFERENCES _user (_u_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE _statement_part_tag ADD CONSTRAINT FK_EDB290AABF396750 FOREIGN KEY (id) REFERENCES statement_part (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE _statement_part_tag ADD CONSTRAINT FK_EDB290AA13C84EE FOREIGN KEY (_t_id) REFERENCES _tag (_t_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE statement_part DROP FOREIGN KEY FK_5B2F70EA849CB65B');
        $this->addSql('ALTER TABLE statement_part DROP FOREIGN KEY FK_5B2F70EA8B50597F');
        $this->addSql('ALTER TABLE statement_part DROP FOREIGN KEY FK_5B2F70EAC33F7837');
        $this->addSql('ALTER TABLE statement_part DROP FOREIGN KEY FK_5B2F70EA1F1F2A24');
        $this->addSql('ALTER TABLE statement_part DROP FOREIGN KEY FK_5B2F70EA59EC7D60');
        $this->addSql('ALTER TABLE _statement_part_tag DROP FOREIGN KEY FK_EDB290AABF396750');
        $this->addSql('ALTER TABLE _statement_part_tag DROP FOREIGN KEY FK_EDB290AA13C84EE');
        $this->addSql('DROP TABLE statement_part');
        $this->addSql('DROP TABLE _statement_part_tag');
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
