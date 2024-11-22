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
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20241122110226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-12914: Add category_id column to institution_tag table. Remove owning_organisation_id column from institution_tag table';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Temporarily disable foreign key checks
        $this->addSql('SET foreign_key_checks = 0;');

        $this->addSql('ALTER TABLE institution_tag ADD category_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE institution_tag ADD CONSTRAINT FK_6C96B95C12469DE2 FOREIGN KEY (category_id) REFERENCES institution_tag_category (id)');
        $this->addSql('CREATE INDEX IDX_6C96B95C12469DE2 ON institution_tag (category_id)');
        // Enable foreign key checks
        $this->addSql('SET foreign_key_checks = 1;');


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
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
