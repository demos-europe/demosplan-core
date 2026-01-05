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

class Version20241121152930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-12914: Add institution tag category';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE institution_tag_category
            (id CHAR(36) NOT NULL,
            customer_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            creation_date DATETIME NOT NULL,
            modification_date DATETIME NOT NULL,
            INDEX IDX_867DAF689395C3F3 (customer_id),
            UNIQUE INDEX unique_category_name_for_customer (customer_id, name),
            PRIMARY KEY(id)) DEFAULT CHARACTER
            SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB'
        );

        $this->addSql('ALTER TABLE institution_tag_category
            ADD CONSTRAINT FK_867DAF689395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (_c_id)'
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE institution_tag_category DROP FOREIGN KEY FK_867DAF689395C3F3');
        $this->addSql('DROP TABLE institution_tag_category');
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
