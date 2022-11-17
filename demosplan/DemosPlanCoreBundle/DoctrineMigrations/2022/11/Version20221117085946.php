<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20221117085946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T29168: to remove the property ´bthg_kompass_answer_id` from the statement entity, a new table ´statements_kompass_answer_relationship`
        is added which is a manytomnay relationship. a statement belongs to an BthgkompassAnswer, and an BthgkompassAnswer can have many statements,
        normally it is a OneToMany or ManyToOne relationship but to avoid any direct relationship between the tables we have to use a ManyToMany
        relation and add a relationship table ´statements_kompass_answer_relationship`';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $matchingRecords = $this->getMatchingRecordsToUp();
        $this->addSql('CREATE TABLE statements_kompass_answer_relationship (kompass_answer_id CHAR(36) NOT NULL, statement_id CHAR(36) NOT NULL, INDEX IDX_29FA2438F2039FF (kompass_answer_id), UNIQUE INDEX UNIQ_29FA2438849CB65B (statement_id), PRIMARY KEY(kompass_answer_id, statement_id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE statements_kompass_answer_relationship ADD CONSTRAINT FK_29FA2438F2039FF FOREIGN KEY (kompass_answer_id) REFERENCES bthg_kompass_answer (id)');
        $this->addSql('ALTER TABLE statements_kompass_answer_relationship ADD CONSTRAINT FK_29FA2438849CB65B FOREIGN KEY (statement_id) REFERENCES _statement (_st_id)');
        foreach ($matchingRecords as $matchingRecord) {
            $this->addSql('INSERT INTO statements_kompass_answer_relationship (statement_id, kompass_answer_id) VALUES (:statementId, :kompassAnswerId)',
                [
                    'statementId'     => $matchingRecord['_st_id'],
                    'kompassAnswerId' => $matchingRecord['bthg_kompass_answer_id'],
                ]);
        }
        $this->addSql('ALTER TABLE _statement DROP FOREIGN KEY FK_8D47F06BC96FC090');
        $this->addSql('ALTER TABLE _statement DROP COLUMN bthg_kompass_answer_id');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $matchingRecords = $this->getMatchingRecordsToDown();
        $this->addSql('ALTER TABLE _statement ADD bthg_kompass_answer_id CHAR(36) NULL');
        $this->addSql('ALTER TABLE _statement ADD CONSTRAINT FK_8D47F06BC96FC090 FOREIGN KEY (bthg_kompass_answer_id) REFERENCES bthg_kompass_answer (id)');
        foreach ($matchingRecords as $matchingRecord) {
            $this->addSql('UPDATE _statement SET bthg_kompass_answer_id = :kompassAnswerId
                        WHERE _st_id = :statementId',
                [
                    'statementId'     => $matchingRecord['statement_id'],
                    'kompassAnswerId' => $matchingRecord['kompass_answer_id'],
                ]);
        }
        $this->addSql('ALTER TABLE statements_kompass_answer_relationship DROP FOREIGN KEY FK_29FA2438F2039FF');
        $this->addSql('ALTER TABLE statements_kompass_answer_relationship DROP FOREIGN KEY FK_29FA2438849CB65B');
        $this->addSql('DROP TABLE statements_kompass_answer_relationship');
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

    private function getMatchingRecordsToUp()
    {
        return $this->connection->fetchAllAssociative('SELECT _st_id, bthg_kompass_answer_id FROM _statement
                                                                        WHERE bthg_kompass_answer_id is NOT NULL');
    }

    private function getMatchingRecordsToDown()
    {
        return $this->connection->fetchAllAssociative('SELECT statement_id, kompass_answer_id FROM statements_kompass_answer_relationship');
    }
}
