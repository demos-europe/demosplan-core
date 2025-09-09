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

class Version20230504164844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T28243: Drop SearchIndexTask completely';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE search_index_task');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE search_index_task (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, created DATETIME NOT NULL, processing TINYINT(1) NOT NULL, INDEX IDX_214833BCE284468 (entity), INDEX IDX_214833BCB23DB7B8 (created), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
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
