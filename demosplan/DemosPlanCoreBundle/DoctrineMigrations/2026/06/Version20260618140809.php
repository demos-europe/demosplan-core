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

class Version20260618140809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-18064: Add isDeleted and deletedDate columns and drop name/customer/audience unique constraint from procedure_phase_definition';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE procedure_phase_definition
                            ADD is_deleted TINYINT(1) DEFAULT 0 NOT NULL,
                            ADD deleted_date DATETIME DEFAULT NULL'
        );
        $this->addSql('DROP INDEX uniq_name_customer_audience ON procedure_phase_definition');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE procedure_phase_definition DROP is_deleted, DROP deleted_date');
        $this->addSql('CREATE UNIQUE INDEX uniq_name_customer_audience ON procedure_phase_definition (name, customer_id, audience)');
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
