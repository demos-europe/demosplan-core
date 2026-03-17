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

/**
 * Add segmentation architecture columns and create text_section table.
 *
 * - order_in_statement: unified ordering for Segments and TextSections within a Statement
 * - segmentation_status: tracks whether a statement uses the new order-based format
 * - edit_locked: prevents structural edits on segments in assessment
 * - text_section table: stores non-segment content blocks
 */
final class Version20260209000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-12697: Add order_in_statement, segmentation_status, edit_locked and text_section table';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Add order_in_statement to _statement table (used by Segment entities)
        $this->addSql('ALTER TABLE _statement ADD COLUMN order_in_statement INT DEFAULT NULL');
        // Populate from existing order_in_procedure values
        $this->addSql('UPDATE _statement SET order_in_statement = order_in_procedure WHERE order_in_procedure IS NOT NULL');

        // Add segmentation fields to _statement table
        $this->addSql("ALTER TABLE _statement ADD _st_segmentation_status VARCHAR(20) NOT NULL DEFAULT 'unsegmented', ADD _st_edit_locked TINYINT(1) DEFAULT 0");

        // Create text_section table
        $this->addSql('CREATE TABLE text_section (id CHAR(36) NOT NULL, statement_id CHAR(36) NOT NULL, order_in_statement INT NOT NULL, text_raw LONGTEXT NOT NULL, text LONGTEXT NOT NULL, INDEX IDX_9D255D0849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE text_section ADD CONSTRAINT FK_9D255D0849CB65B FOREIGN KEY (statement_id) REFERENCES _statement (_st_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE text_section DROP FOREIGN KEY FK_9D255D0849CB65B');
        $this->addSql('DROP TABLE text_section');
        $this->addSql('ALTER TABLE _statement DROP _st_segmentation_status, DROP _st_edit_locked');
        $this->addSql('ALTER TABLE _statement DROP COLUMN order_in_statement');
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
