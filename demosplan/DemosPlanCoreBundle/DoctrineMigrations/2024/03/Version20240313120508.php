<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Faker\Provider\Uuid;

class Version20240313120508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T36340: Extract phase of an procedure into own entity. Step2: Copy current data into new entity.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $procedurePhases = $this->getPhaseDataOfAllProcedures();

        foreach ($procedurePhases as $phase) {
            $this->addInternalPhase($phase);
            $this->addExternalPhase($phase);
        }
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySqlPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }

    private function addInternalPhase(array $phase): void
    {
        $phaseId = Uuid::uuid();
        $this->addSql(
            'INSERT INTO procedure_phase SET
                `id` = :uuid,
                `designated_phase_change_user_id` = :designated_phase_change_user_id,
                `key` = :key,
                `step` = :step,
                `start_date` = :start_date,
                `end_date` = :end_date,
                `designated_phase` = :designated_phase,
                `designated_switch_date` = :designated_switch_date,
                `designated_end_date` = :designated_end_date,
                `creation_date` = NOW(),
                `modification_date` = NOW()
            ',[
            'designated_phase_change_user_id' => $phase['designatedPhaseChangeUser'],
            'uuid' => $phaseId,
            'key' => $phase['key'],
            'step' => $phase['step'],
            'start_date' => $phase['startDate'],
            'end_date' => $phase['endDate'],
            'designated_phase' => $phase['designatedPhase'],
            'designated_switch_date' => $phase['designatedSwitchDate'],
            'designated_end_date' => $phase['designatedEndDate'],
        ]);

        $this->addSql(
            'UPDATE _procedure SET phase_id =:phaseId WHERE _p_id =:procedureId',
            [
                'phaseId' => $phaseId,
                'procedureId' => $phase['procedureId'],
            ]
        );

    }

    private function addExternalPhase(array $phase): void
    {
        $phaseId = Uuid::uuid();
        $this->addSql(
            'INSERT INTO procedure_phase SET
                `id` = :uuid,
                `designated_phase_change_user_id` = :designated_phase_change_user_id,
                `key` = :key,
                `step` = :step,
                `start_date` = :start_date,
                `end_date` = :end_date,
                `designated_phase` = :designated_phase,
                `designated_switch_date` = :designated_switch_date,
                `designated_end_date` = :designated_end_date,
                `creation_date` = NOW(),
                `modification_date` = NOW()
            ',[
            'designated_phase_change_user_id' => $phase['designatedExternalPhaseChangeUser'],
            'uuid' => $phaseId,
            'key' => $phase['externalKey'],
            'step' => $phase['externalStep'],
            'start_date' => $phase['externalStartDate'],
            'end_date' => $phase['externalEndDate'],
            'designated_phase' => $phase['designatedExternalPhase'],
            'designated_switch_date' => $phase['designatedExternalSwitchDate'],
            'designated_end_date' => $phase['designatedExternalEndDate'],
        ]);

        $this->addSql(
            'UPDATE _procedure SET public_participation_phase_id =:phaseId WHERE _p_id =:procedureId',
            [
                'phaseId' => $phaseId,
                'procedureId' => $phase['procedureId'],
            ]
        );

    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function getPhaseDataOfAllProcedures(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                _procedure._p_id as `procedureId`,
                _p_name as `name`,
                _p_phase as `key`,
                _p_public_participation_phase as `externalKey`,
                _p_step as `step`,
                _p_public_participation_step as `externalStep`,
                _ps_designated_phase as `designatedPhase`,
                _ps_designated_public_phase as `designatedExternalPhase`,
                _p_start_date as `startDate`,
                _p_public_participation_start as `externalStartDate`,
                _p_end_date as `endDate`,
                _p_public_participation_end as `externalEndDate`,
                _ps_designated_switch_date as `designatedSwitchDate`,
                _ps_designated_public_switch_date as `designatedExternalSwitchDate`,
                _ps_designated_end_date as `designatedEndDate`,
                _ps_designated_public_end_date as `designatedExternalEndDate`,
                _procedure_settings.designated_phase_change_user_id as `designatedPhaseChangeUser`,
                _procedure_settings.designated_public_phase_change_user_id as `designatedExternalPhaseChangeUser`
                FROM _procedure INNER JOIN _procedure_settings
                ON _procedure._p_id = _procedure_settings._p_id;');
    }
}
