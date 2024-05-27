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

class Version20240318172346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T36340: Extract phase of an procedure into own entity.
        Step3: Make phase of a procedure not nullable, because at least an internal phase is always needed.
        (This was nullable true due the initial data migration.)';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // mariadb 10.1 needs to drop the foreign key before changing the column
        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY FK_D1A01D0299091188');
        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY FK_D1A01D0230F7E25B');
        $this->addSql('
            ALTER TABLE _procedure
            CHANGE phase_id phase_id CHAR(36) NOT NULL
        ');

        $this->addSql('
            ALTER TABLE _procedure
            CHANGE public_participation_phase_id public_participation_phase_id CHAR(36) NOT NULL
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
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('
            ALTER TABLE _procedure CHANGE
            phase_id phase_id CHAR(36) DEFAULT NULL
        ');

        $this->addSql('
            ALTER TABLE _procedure
            CHANGE public_participation_phase_id public_participation_phase_id CHAR(36) DEFAULT NULL
        ');
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
