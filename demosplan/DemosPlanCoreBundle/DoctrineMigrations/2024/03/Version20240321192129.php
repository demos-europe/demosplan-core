<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240321192129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T36340: Extract phase of an procedure into own entity. Step4: Remove obsolete fields of procedure table.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure DROP _p_phase, DROP _p_step, DROP _p_public_participation_phase, DROP _p_public_participation_step, DROP _p_public_participation_start, DROP _p_public_participation_end, DROP _p_start_date, DROP _p_end_date');
        $this->addSql('ALTER TABLE _procedure_settings DROP FOREIGN KEY FK_9C04F53DF3EE7A28');
        $this->addSql('ALTER TABLE _procedure_settings DROP FOREIGN KEY FK_9C04F53DCBD82728');
        $this->addSql('DROP INDEX IDX_9C04F53DCBD82728 ON _procedure_settings');
        $this->addSql('DROP INDEX IDX_9C04F53DF3EE7A28 ON _procedure_settings');
        $this->addSql('ALTER TABLE _procedure_settings DROP designated_phase_change_user_id, DROP designated_public_phase_change_user_id, DROP _ps_designated_phase, DROP _ps_designated_public_phase');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _procedure_settings ADD designated_phase_change_user_id CHAR(36) DEFAULT NULL, ADD designated_public_phase_change_user_id CHAR(36) DEFAULT NULL, ADD _ps_designated_phase VARCHAR(50) DEFAULT NULL, ADD _ps_designated_public_phase VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE _procedure_settings ADD CONSTRAINT FK_9C04F53DF3EE7A28 FOREIGN KEY (designated_public_phase_change_user_id) REFERENCES _user (_u_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE _procedure_settings ADD CONSTRAINT FK_9C04F53DCBD82728 FOREIGN KEY (designated_phase_change_user_id) REFERENCES _user (_u_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9C04F53DCBD82728 ON _procedure_settings (designated_phase_change_user_id)');
        $this->addSql('CREATE INDEX IDX_9C04F53DF3EE7A28 ON _procedure_settings (designated_public_phase_change_user_id)');
        $this->addSql('ALTER TABLE _procedure ADD _p_phase VARCHAR(255) NOT NULL, ADD _p_step VARCHAR(25) DEFAULT \'\' NOT NULL, ADD _p_public_participation_phase VARCHAR(255) NOT NULL, ADD _p_public_participation_step VARCHAR(25) DEFAULT \'\' NOT NULL, ADD _p_public_participation_start DATETIME NOT NULL, ADD _p_public_participation_end DATETIME NOT NULL, ADD _p_start_date DATETIME NOT NULL, ADD _p_end_date DATETIME NOT NULL');
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
