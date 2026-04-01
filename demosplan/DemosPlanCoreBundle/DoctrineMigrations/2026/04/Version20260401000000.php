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

class Version20260401000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add StatementGroup support: backfill entity_type discriminator and add group columns to _statement';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Backfill existing cluster heads to the new discriminator value.
        // Only rows that are still 'Statement' are touched — idempotent if run twice.
        $this->addSql("UPDATE _statement SET entity_type = 'StatementGroup' WHERE cluster_statement = 1 AND entity_type = 'Statement'");

        // Add group-specific nullable columns (no default data needed — existing rows stay NULL)
        $this->addSql('ALTER TABLE _statement ADD group_name VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE _statement ADD group_representative_id CHAR(36) DEFAULT NULL');
        $this->addSql("ALTER TABLE _statement ADD group_created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");

        // FK from group_representative_id back into _statement, with an index for join performance
        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE _statement ADD CONSTRAINT FK_statement_group_representative FOREIGN KEY (group_representative_id) REFERENCES _statement (_st_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_statement_group_representative ON _statement (group_representative_id)');
        $this->addSql('SET foreign_key_checks = 1');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE _statement DROP FOREIGN KEY FK_statement_group_representative');
        $this->addSql('DROP INDEX IDX_statement_group_representative ON _statement');
        $this->addSql('SET foreign_key_checks = 1');

        $this->addSql('ALTER TABLE _statement DROP COLUMN group_representative_id');
        $this->addSql('ALTER TABLE _statement DROP COLUMN group_name');
        $this->addSql('ALTER TABLE _statement DROP COLUMN group_created_at');

        // Revert discriminator — StatementGroup rows become plain Statement rows again
        $this->addSql("UPDATE _statement SET entity_type = 'Statement' WHERE entity_type = 'StatementGroup'");
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
