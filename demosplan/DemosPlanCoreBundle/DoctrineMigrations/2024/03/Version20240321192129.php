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

class Version20240321192129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T36340: Extract phase of an procedure into own entity.
         Step4: Remove obsolete fields (including its data) of procedure table.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure_settings DROP FOREIGN KEY FK_9C04F53DF3EE7A28');
        $this->addSql('ALTER TABLE _procedure_settings DROP FOREIGN KEY FK_9C04F53DCBD82728');
        $this->addSql('DROP INDEX IDX_9C04F53DCBD82728 ON _procedure_settings');
        $this->addSql('DROP INDEX IDX_9C04F53DF3EE7A28 ON _procedure_settings');

        $this->addSql('
            ALTER TABLE _procedure
            DROP _p_phase,
            DROP _p_step,
            DROP _p_public_participation_phase,
            DROP _p_public_participation_step,
            DROP _p_public_participation_start,
            DROP _p_public_participation_end,
            DROP _p_start_date,
            DROP _p_end_date
        ');

        $this->addSql('
            ALTER TABLE _procedure_settings
            DROP designated_phase_change_user_id,
            DROP designated_public_phase_change_user_id,
            DROP _ps_designated_phase,
            DROP _ps_designated_public_phase,
            DROP _ps_designated_switch_date,
            DROP _ps_designated_public_switch_date,
            DROP _ps_designated_end_date,
            DROP _ps_designated_public_end_date
        ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->recreateOldColumns();
        $this->writePhaseDataInOldColumns();
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

    private function getPhase(string $phaseId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                id,
                phase_key,
                step,
                designated_phase,
                start_date,
                end_date,
                designated_switch_date,
                designated_end_date,
                designated_phase_change_user_id
                FROM procedure_phase
                WHERE id =:phaseId',
            ['phaseId' => $phaseId]
        );
    }

    private function setExternalPhaseDataToProcedure(array $externalPhasePhase, string $procedureId): void
    {
        $this->addSql(
            'UPDATE _procedure SET
                `_p_public_participation_phase` = :phase_key,
                `_p_public_participation_step` = :step,
                `_p_public_participation_start` = :start_date,
                `_p_public_participation_end` = :end_date
                WHERE _p_id = :procedureId',
            [
                'procedureId'                     => $procedureId,
                'phase_key'                       => $externalPhasePhase['phase_key'],
                'step'                            => $externalPhasePhase['step'],
                'start_date'                      => $externalPhasePhase['start_date'],
                'end_date'                        => $externalPhasePhase['end_date'],
            ]
        );
    }

    private function setExternalPhaseDataToProcedureSetting(array $externalPhasePhase, string $procedureId): void
    {
        $this->addSql(
            'UPDATE _procedure_settings SET
                `designated_public_phase_change_user_id` = :designated_phase_change_user_id,
                `_ps_designated_public_phase` = :designated_phase,
                `_ps_designated_public_switch_date` = :designated_switch_date,
                `_ps_designated_public_end_date` = :designated_end_date
                WHERE _p_id =:procedureId',
            [
                'procedureId'                     => $procedureId,
                'designated_phase_change_user_id' => $externalPhasePhase['designated_phase_change_user_id'],
                'designated_phase'                => $externalPhasePhase['designated_phase'],
                'designated_switch_date'          => $externalPhasePhase['designated_switch_date'],
                'designated_end_date'             => $externalPhasePhase['designated_end_date'],
            ]
        );
    }

    private function setInternalPhaseDataToProcedure(array $internalPhase, string $procedureId): void
    {
        $this->addSql(
            'UPDATE _procedure SET
                `_p_phase` = :phase_key,
                `_p_step` = :step,
                `_p_start_date` = :start_date,
                `_p_end_date` = :end_date
                WHERE _p_id = :procedureId',
            [
                'procedureId'                     => $procedureId,
                'phase_key'                       => $internalPhase['phase_key'],
                'step'                            => $internalPhase['step'],
                'start_date'                      => $internalPhase['start_date'],
                'end_date'                        => $internalPhase['end_date'],
            ]
        );
    }

    private function setInternalPhaseDataToProcedureSetting(array $internalPhase, string $procedureId): void
    {
        $this->addSql(
            'UPDATE _procedure_settings SET
                `designated_phase_change_user_id` = :designated_phase_change_user_id,
                `_ps_designated_phase` = :designated_phase,
                `_ps_designated_switch_date` = :designated_switch_date,
                `_ps_designated_end_date` = :designated_end_date
                WHERE _p_id =:procedureId',
            [
                'procedureId'                     => $procedureId,
                'designated_phase_change_user_id' => $internalPhase['designated_phase_change_user_id'],
                'designated_phase'                => $internalPhase['designated_phase'],
                'designated_switch_date'          => $internalPhase['designated_switch_date'],
                'designated_end_date'             => $internalPhase['designated_end_date'],
            ]
        );
    }

    /**
     * @throws Exception
     */
    private function getProcedures(): array
    {
        return $this->connection->fetchAllAssociative(
            ' SELECT
                _p_id,
                phase_id,
                public_participation_phase_id
                FROM _procedure
        ');
    }

    private function recreateOldColumns(): void
    {
        $this->addSql('
            ALTER TABLE _procedure_settings
            ADD designated_phase_change_user_id CHAR(36) DEFAULT NULL,
            ADD designated_public_phase_change_user_id CHAR(36) DEFAULT NULL,
            ADD _ps_designated_phase VARCHAR(50) DEFAULT NULL,
            ADD _ps_designated_public_phase DATETIME DEFAULT NULL,
            ADD _ps_designated_switch_date VARCHAR(50) DEFAULT NULL,
            ADD _ps_designated_public_switch_date DATETIME DEFAULT NULL,
            ADD _ps_designated_end_date DATETIME DEFAULT NULL,
            ADD _ps_designated_public_end_date DATETIME DEFAULT NULL
        ');

        $this->addSql('
            ALTER TABLE _procedure_settings
            ADD CONSTRAINT FK_9C04F53DF3EE7A28 FOREIGN KEY ( designated_public_phase_change_user_id )
            REFERENCES _user (_u_id) ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE _procedure_settings
            ADD CONSTRAINT FK_9C04F53DCBD82728 FOREIGN KEY (designated_phase_change_user_id)
            REFERENCES _user (_u_id) ON DELETE SET NULL
        ');

        $this->addSql('
            CREATE INDEX IDX_9C04F53DCBD82728 ON _procedure_settings
            (designated_phase_change_user_id)
        ');

        $this->addSql('
            CREATE INDEX IDX_9C04F53DF3EE7A28 ON _procedure_settings
            (designated_public_phase_change_user_id)
        ');

        $this->addSql('
            ALTER TABLE _procedure
            ADD _p_phase VARCHAR(255) NOT NULL,
            ADD _p_step VARCHAR(25) DEFAULT \'\' NOT NULL,
            ADD _p_public_participation_phase VARCHAR(255) NOT NULL,
            ADD _p_public_participation_step VARCHAR(25) DEFAULT \'\' NOT NULL,
            ADD _p_public_participation_start DATETIME NOT NULL DEFAULT \'1970-01-01 02:01:01.000\',
            ADD _p_public_participation_end DATETIME NOT NULL DEFAULT \'1970-01-01 02:01:01.000\',
            ADD _p_start_date DATETIME NOT NULL DEFAULT \'1970-01-01 02:01:01.000\',
            ADD _p_end_date DATETIME NOT NULL DEFAULT \'1970-01-01 02:01:01.000\'
        ');
    }

    private function writePhaseDataInOldColumns(): void
    {
        $procedures = $this->getProcedures();

        foreach ($procedures as $procedure) {
            $internalPhase = $this->getPhase($procedure['phase_id'])[0];
            $this->setInternalPhaseDataToProcedure($internalPhase, $procedure['_p_id']);
            $this->setInternalPhaseDataToProcedureSetting($internalPhase, $procedure['_p_id']);

            $externalPhase = $this->getPhase($procedure['public_participation_phase_id'])[0];
            $this->setExternalPhaseDataToProcedure($externalPhase, $procedure['_p_id']);
            $this->setExternalPhaseDataToProcedureSetting($externalPhase, $procedure['_p_id']);
        }
    }
}
