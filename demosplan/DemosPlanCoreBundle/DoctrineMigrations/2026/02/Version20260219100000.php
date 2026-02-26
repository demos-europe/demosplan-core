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

class Version20260219100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-16766: Create procedure_phase_definition table for customer-specific procedure phase definitions';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql(
            'CREATE TABLE procedure_phase_definition (
                id                  CHAR(36)     NOT NULL,
                customer_id         CHAR(36)     NOT NULL,
                name                VARCHAR(255) NOT NULL,
                audience            VARCHAR(25)  NOT NULL,
                permission_set      VARCHAR(10)  NOT NULL,
                participation_state VARCHAR(50)  DEFAULT NULL,
                order_in_audience   INT UNSIGNED NOT NULL DEFAULT 0,
                creation_date       DATETIME     NOT NULL,
                modification_date   DATETIME     NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_name_customer_audience (name, customer_id, audience),
                KEY idx_ppd_customer (customer_id),
                CONSTRAINT fk_ppd_customer FOREIGN KEY (customer_id)
                    REFERENCES customer (_c_id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE procedure_phase_definition');
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
