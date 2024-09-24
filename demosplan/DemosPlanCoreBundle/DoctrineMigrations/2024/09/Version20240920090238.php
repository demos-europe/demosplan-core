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

class Version20240920090238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-11379: Adjust _platform_content to use file entity';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _platform_content ADD picture_id CHAR(36) DEFAULT NULL COMMENT \'This id is used to reference to the file entity\'');
        $this->addSql('ALTER TABLE _platform_content ADD CONSTRAINT FK_42348F4FEE45BDBF FOREIGN KEY (picture_id) REFERENCES _files (_f_ident) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_42348F4FEE45BDBF ON _platform_content (picture_id)');

        $this->addSql('ALTER TABLE _platform_content ADD pdf_id CHAR(36) DEFAULT NULL COMMENT \'This id is used in filestrings to reference to the file entity\'');
        $this->addSql('ALTER TABLE _platform_content ADD CONSTRAINT FK_42348F4F511FC912 FOREIGN KEY (pdf_id) REFERENCES _files (_f_ident) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_42348F4F511FC912 ON _platform_content (pdf_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _platform_content DROP FOREIGN KEY FK_42348F4FEE45BDBF');
        $this->addSql('DROP INDEX IDX_42348F4FEE45BDBF ON _platform_content');
        $this->addSql('ALTER TABLE _platform_content DROP picture_id');

        $this->addSql('ALTER TABLE _platform_content DROP FOREIGN KEY FK_42348F4F511FC912');
        $this->addSql('DROP INDEX IDX_42348F4F511FC912 ON _platform_content');
        $this->addSql('ALTER TABLE _platform_content DROP pdf_id');
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
