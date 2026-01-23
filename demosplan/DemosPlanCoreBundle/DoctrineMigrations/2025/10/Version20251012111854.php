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

class Version20251012111854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-12697: Add segmentation architecture v2 - TextSection table, segmentationStatus and editLocked fields';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Create text_section table
        $this->addSql('CREATE TABLE text_section (id CHAR(36) NOT NULL, statement_id CHAR(36) NOT NULL, order_in_statement INT NOT NULL, text_raw LONGTEXT NOT NULL, text LONGTEXT NOT NULL, section_type VARCHAR(20) NOT NULL, INDEX IDX_9D255D0849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE text_section ADD CONSTRAINT FK_9D255D0849CB65B FOREIGN KEY (statement_id) REFERENCES _statement (_st_id) ON DELETE CASCADE');

        // Add segmentation fields to _statement table
        $this->addSql('ALTER TABLE _statement ADD _st_segmentation_status VARCHAR(20) NOT NULL DEFAULT \'unsegmented\', ADD _st_edit_locked TINYINT(1) DEFAULT 0');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Drop text_section table
        $this->addSql('ALTER TABLE text_section DROP FOREIGN KEY FK_9D255D0849CB65B');
        $this->addSql('DROP TABLE text_section');

        // Remove segmentation fields from _statement table
        $this->addSql('ALTER TABLE _statement DROP _st_segmentation_status, DROP _st_edit_locked');
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
