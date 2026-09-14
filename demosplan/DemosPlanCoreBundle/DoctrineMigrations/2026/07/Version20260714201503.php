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

class Version20260714201503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-18197: add boilerplate_usage table to track boilerplates inserted into segment recommendations';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE boilerplate_usage (id CHAR(36) NOT NULL, boilerplate_id CHAR(36) NOT NULL, segment_id CHAR(36) NOT NULL, create_date DATETIME NOT NULL, INDEX IDX_F4302AEA29617DCB (boilerplate_id), INDEX IDX_F4302AEADB296AAD (segment_id), UNIQUE INDEX unique_boilerplate_segment (boilerplate_id, segment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE boilerplate_usage ADD CONSTRAINT FK_F4302AEA29617DCB FOREIGN KEY (boilerplate_id) REFERENCES _predefined_texts (_pt_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE boilerplate_usage ADD CONSTRAINT FK_F4302AEADB296AAD FOREIGN KEY (segment_id) REFERENCES _statement (_st_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE boilerplate_usage DROP FOREIGN KEY FK_F4302AEA29617DCB');
        $this->addSql('ALTER TABLE boilerplate_usage DROP FOREIGN KEY FK_F4302AEADB296AAD');
        $this->addSql('DROP TABLE boilerplate_usage');
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
