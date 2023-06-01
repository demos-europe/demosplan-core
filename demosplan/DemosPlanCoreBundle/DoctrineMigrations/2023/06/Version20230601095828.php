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

class Version20230601095828 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T32934: Improve performance on Assessment Table by adding database index';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE _statement ADD CONSTRAINT FK_8D47F06B84040EA6 FOREIGN KEY (segment_statement_fk) REFERENCES _statement (_st_id)');
        $this->addSql('CREATE INDEX IDX_8D47F06B84040EA6 ON _statement (segment_statement_fk)');
        $this->addSql('SET foreign_key_checks = 1');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE _statement DROP FOREIGN KEY FK_8D47F06B84040EA6');
        $this->addSql('DROP INDEX IDX_8D47F06B84040EA6 ON _statement');
        $this->addSql('SET foreign_key_checks = 1');
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
