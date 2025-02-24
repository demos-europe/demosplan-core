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

class Version20250224070116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs TADO24405: use cascade delete for predefined_texts_categories';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE predefined_texts_categories DROP FOREIGN KEY FK_843996AFFF124921');
        $this->addSql('ALTER TABLE predefined_texts_categories DROP FOREIGN KEY FK_843996AFC895D0A7');
        $this->addSql('ALTER TABLE predefined_texts_categories ADD CONSTRAINT FK_843996AFFF124921 FOREIGN KEY (_ptc_id) REFERENCES _predefined_texts_category (ptc_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE predefined_texts_categories ADD CONSTRAINT FK_843996AFC895D0A7 FOREIGN KEY (_pt_id) REFERENCES _predefined_texts (_pt_id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE predefined_texts_categories DROP FOREIGN KEY FK_843996AFFF124921');
        $this->addSql('ALTER TABLE predefined_texts_categories DROP FOREIGN KEY FK_843996AFC895D0A7');
        $this->addSql('ALTER TABLE predefined_texts_categories ADD CONSTRAINT FK_843996AFFF124921 FOREIGN KEY (_ptc_id) REFERENCES _predefined_texts_category (ptc_id)');
        $this->addSql('ALTER TABLE predefined_texts_categories ADD CONSTRAINT FK_843996AFC895D0A7 FOREIGN KEY (_pt_id) REFERENCES _predefined_texts (_pt_id)');
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
