<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231023103423 extends AbstractMigration
{
    private const CHANGE_WAS_IST_DIPLANBETEILIGUNG_OLD = '<p>DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, insbesondere im Bauwesen (aktuell Bauleitplanung, später Landesplanung und Planfeststellung), einfach und effizient ermöglicht. Sie nehmen entweder als Bürger*in, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. eines Träger öffentlicher Belange (TöB) an diesem Verfahren teil. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.</p>';
    private const CHANGE_WAS_IST_DIPLANBETEILIGUNG_NEW = '<p>DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, insbesondere im Bauwesen (Bauleitplanung, Raumordnung und Planfeststellung), einfach und effizient ermöglicht. Sie nehmen entweder als Bürger*in, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. eines Trägers öffentlicher Belange (TöB) an diesem Verfahren teil. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.</p>';

    public function getDescription(): string
    {
        return 'refs T34604: Adjust platform faqs for diplanrog';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $roleIds = [
            'FP' => [
                'RMOPSD' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPSD";'),
                'RMOPSA' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPSA";'),
                'RMOPFB' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPFB";'),
                'RMOPPO' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPPO";'),
            ],
            'Institutions' => [
                'RPSOCO' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RPSOCO";'),
                'RPSODE' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RPSODE";'),
            ],
            'public' => [
                'RGUEST' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RGUEST";'),
                'RCITIZ' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RCITIZ";'),
            ],
        ];

        $this->abortIfNotMysql();
        $this->addSql(
            'UPDATE platform_faq SET text = :newText WHERE title = :title',
            ['title' => 'Was ist DiPlanBeteiligung?', 'newText' => self::CHANGE_WAS_IST_DIPLANBETEILIGUNG_NEW]
        );



    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql(
            'UPDATE platform_faq SET text = :newText WHERE title = :title',
            ['title' => 'Was ist DiPlanBeteiligung?', 'newText' => self::CHANGE_WAS_IST_DIPLANBETEILIGUNG_OLD]
        );

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
}
