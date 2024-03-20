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

class Version20240313115724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T36340: Extract phase of an procedure into own entity. Step1: Create new related entity.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            CREATE TABLE procedure_phase
                (id CHAR(36) NOT NULL,
                designated_phase_change_user_id CHAR(36) DEFAULT NULL,
                `key` VARCHAR(255) NOT NULL,
                step VARCHAR(25) DEFAULT \'\' NOT NULL,
                start_date DATETIME NOT NULL, end_date DATETIME NOT NULL,
                creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL,
                designated_phase VARCHAR(50) DEFAULT NULL,
                designated_switch_date DATETIME DEFAULT NULL,
                designated_end_date DATETIME DEFAULT NULL,
                INDEX IDX_52C66981CBD82728 (designated_phase_change_user_id),
            PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB
        ');
        $this->addSql('
            ALTER TABLE procedure_phase
                ADD CONSTRAINT FK_52C66981CBD82728 FOREIGN KEY (designated_phase_change_user_id)
                REFERENCES _user (_u_id)
                ON DELETE SET NULL
        ');

        // Create as nullable first, to allow creation of columns, afterwards it will be changed to
        // nullable = false, because a procedure has to have a related phases in first place.
        $this->addSql('
            ALTER TABLE _procedure
                ADD phase_id CHAR(36) DEFAULT NULL,
                ADD public_participation_phase_id CHAR(36) DEFAULT NULL
        ');

        $this->addSql('
            ALTER TABLE _procedure
                ADD CONSTRAINT FK_D1A01D0299091188 FOREIGN KEY (phase_id)
                REFERENCES procedure_phase (id)
        ');

        $this->addSql('
            ALTER TABLE _procedure
                ADD CONSTRAINT FK_D1A01D0230F7E25B FOREIGN KEY (public_participation_phase_id)
                REFERENCES procedure_phase (id)
        ');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_D1A01D0299091188 ON _procedure (phase_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D1A01D0230F7E25B ON _procedure (public_participation_phase_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY FK_D1A01D0299091188');
        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY FK_D1A01D0230F7E25B');
        $this->addSql('ALTER TABLE procedure_phase DROP FOREIGN KEY FK_52C66981CBD82728');
        $this->addSql('DROP TABLE procedure_phase');
        $this->addSql('DROP INDEX UNIQ_D1A01D0299091188 ON _procedure');
        $this->addSql('DROP INDEX UNIQ_D1A01D0230F7E25B ON _procedure');
        $this->addSql('ALTER TABLE _procedure DROP phase_id, DROP public_participation_phase_id');
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
