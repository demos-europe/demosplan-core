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

class Version20220928083055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T29168: add a new proprety ´procedureId` in maillane_connection,procedure ids with a maillane connection
         will be copied there and the proprety `maillane_connection_id` will be removed from procedure';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $matchingRecords = $this->getMatchingRecordsToUp();
        $this->addSql('ALTER TABLE maillane_connection ADD procedure_id CHAR(36) NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_71C04D1D1624BCD2 ON maillane_connection (procedure_id)');
        foreach ($matchingRecords as $matchingRecord) {
            $this->addSql('UPDATE maillane_connection SET procedure_id = :procedureId
                        WHERE id = :maillaneConnectionId',
                [
                    'procedureId'          => $matchingRecord['_p_id'],
                    'maillaneConnectionId' => $matchingRecord['maillane_connection_id'],
                ]);
        }
        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY FK_D1A01D02AC0C069A');
        $this->addSql('DROP INDEX UNIQ_D1A01D02AC0C069A ON _procedure');
        $this->addSql('ALTER TABLE _procedure DROP COLUMN maillane_connection_id');
        $this->addSql('DELETE FROM maillane_connection WHERE procedure_id is NULL');
        $this->addSql('ALTER TABLE maillane_connection CHANGE procedure_id procedure_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE maillane_connection ADD CONSTRAINT FK_71C04D1D1624BCD2 FOREIGN KEY (procedure_id) REFERENCES _procedure (_p_id)');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $matchingRecords = $this->getMatchingRecordsToDown();
        $this->addSql('ALTER TABLE _procedure ADD maillane_connection_id CHAR(36) NULL');
        $this->addSql('ALTER TABLE _procedure ADD CONSTRAINT FK_D1A01D02AC0C069A FOREIGN KEY (maillane_connection_id) REFERENCES maillane_connection (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D1A01D02AC0C069A ON _procedure (maillane_connection_id)');
        foreach ($matchingRecords as $matchingRecord) {
            $this->addSql('UPDATE _procedure SET maillane_connection_id = :maillaneConnectionId
                        WHERE _p_id = :procedureId',
                [
                    'procedureId'          => $matchingRecord['procedure_id'],
                    'maillaneConnectionId' => $matchingRecord['id'],
                ]);
        }
        $this->addSql('ALTER TABLE maillane_connection DROP FOREIGN KEY FK_71C04D1D1624BCD2');
        $this->addSql('DROP INDEX UNIQ_71C04D1D1624BCD2 ON maillane_connection');
        $this->addSql('ALTER TABLE maillane_connection DROP COLUMN procedure_id');
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

    public function getMatchingRecordsToUp()
    {
        return $this->connection->fetchAllAssociative('SELECT _p_id,maillane_connection_id  
                                                            FROM maillane_connection
                                                            INNER JOIN _procedure ON maillane_connection.id = _procedure.maillane_connection_id
                                                            ');
    }

    public function getMatchingRecordsToDown()
    {
        return $this->connection->fetchAllAssociative('SELECT id, procedure_id FROM maillane_connection');
    }
}
