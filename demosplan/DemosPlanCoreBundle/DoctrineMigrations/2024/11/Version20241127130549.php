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

class Version20241127130549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-12914: Add unique index for label and category and delete all entries in orga_institution_tag and institution_tag';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('SET foreign_key_checks = 0;');
        $this->addSql('TRUNCATE TABLE orga_institution_tag');
        $this->addSql('TRUNCATE TABLE institution_tag');
        $this->addSql('SET foreign_key_checks = 1;');
        $this->addSql('CREATE UNIQUE INDEX unique_label_for_category ON institution_tag (category_id, label)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP INDEX unique_label_for_category ON institution_tag');
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
