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

class Version20260807160723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-17722: rename user_filter_set table to bookmark';
    }

    /**
     * Renamed rather than recreated, because the table holds production data: named
     * assessment-table filter sets.
     *
     * Doctrine derives index names from a hash of the table name, so all three move with it and have
     * to be renamed too - otherwise the schema stays permanently out of sync and every future
     * `dplan:migrations:diff` reports drift. The names are identical in every environment: both the
     * core migration that created the table and the raw SQL dump in
     * `projects/bimschgsh/.../Version20181112144919.php` hardcode them.
     *
     * Foreign keys deliberately keep their `FK_A6A762E2*` names: Doctrine compares foreign key
     * definitions rather than their names, so a rename is not needed and dropping and re-adding them
     * would only open a window without enforced integrity. Verified with
     * `doctrine:schema:update --dump-sql`, which asks for the index renames and nothing else.
     *
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if (!$schema->hasTable('user_filter_set')) {
            return;
        }

        $this->addSql('RENAME TABLE user_filter_set TO bookmark');

        $this->addSql('ALTER TABLE bookmark RENAME INDEX IDX_A6A762E2A76ED395 TO IDX_DA62921DA76ED395');
        $this->addSql('ALTER TABLE bookmark RENAME INDEX IDX_A6A762E23DD05366 TO IDX_DA62921D3DD05366');
        $this->addSql('ALTER TABLE bookmark RENAME INDEX IDX_A6A762E21624BCD2 TO IDX_DA62921D1624BCD2');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        if (!$schema->hasTable('bookmark')) {
            return;
        }

        $this->addSql('ALTER TABLE bookmark RENAME INDEX IDX_DA62921DA76ED395 TO IDX_A6A762E2A76ED395');
        $this->addSql('ALTER TABLE bookmark RENAME INDEX IDX_DA62921D3DD05366 TO IDX_A6A762E23DD05366');
        $this->addSql('ALTER TABLE bookmark RENAME INDEX IDX_DA62921D1624BCD2 TO IDX_A6A762E21624BCD2');

        $this->addSql('RENAME TABLE bookmark TO user_filter_set');
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
