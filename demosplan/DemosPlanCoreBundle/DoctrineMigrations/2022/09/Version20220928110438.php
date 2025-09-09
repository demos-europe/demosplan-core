<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

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

        $this->addSql('CREATE TABLE orga_institution_tag (orga__o_id CHAR(36) NOT NULL, institution_tag_id CHAR(36) NOT NULL, INDEX IDX_5F64F7EA57022B64 (orga__o_id), PRIMARY KEY(orga__o_id, institution_tag_id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE institution_tag (id CHAR(36) NOT NULL, owning_organisation_id CHAR(36) NOT NULL, `label` VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, INDEX IDX_6C96B95C56E11002 (owning_organisation_id), UNIQUE INDEX unique_label_for_orga (owning_organisation_id, `label`), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE orga_institution_tag ADD CONSTRAINT FK_5F64F7EA57022B64 FOREIGN KEY (orga__o_id) REFERENCES _orga (_o_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orga_institution_tag ADD CONSTRAINT FK_5F64F7EA2F8C3108 FOREIGN KEY (institution_tag_id) REFERENCES institution_tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE institution_tag ADD CONSTRAINT FK_6C96B95C56E11002 FOREIGN KEY (owning_organisation_id) REFERENCES _orga (_o_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE orga_institution_tag DROP FOREIGN KEY FK_5F64F7EA2F8C3108');
        $this->addSql('DROP TABLE orga_institution_tag');
        $this->addSql('DROP TABLE institution_tag');
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
