<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Version20220928083055 extends AbstractMigration implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    private const RESTRICT_TO_PROJECT = 'blp';

    public function getDescription(): string
    {
        return 'refs T: test Migration';

    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        if (self::RESTRICT_TO_PROJECT !== $this->container->getParameter('project_folder')){
            return;
        }

        $matchingRecords = $this->getMatchingRecordsToUp();
        $this->abortIfNotMysql();
        $this->addSql('ALTER TABLE maillane_connection ADD procedure_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE maillane_connection ADD CONSTRAINT FK_71C04D1D1624BCD2 FOREIGN KEY (procedure_id) REFERENCES _procedure (_p_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_71C04D1D1624BCD2 ON maillane_connection (procedure_id)');
        foreach ($matchingRecords as $matchingRecord) {
            $this->addSql('UPDATE maillane_connection SET procedure_id = :procedureId
                        WHERE id = :maillaneConnectionId' ,
                [
                    'procedureId' => $matchingRecord['_p_id'],
                    'maillaneConnectionId' => $matchingRecord['maillane_connection_id']
                ]);
        }
        $this->addSql('ALTER TABLE _procedure DROP FOREIGN KEY FK_D1A01D02AC0C069A');
        $this->addSql('DROP INDEX UNIQ_D1A01D02AC0C069A ON _procedure');
        $this->addSql('ALTER TABLE _procedure DROP COLUMN maillane_connection_id');
        //$this->addSql('ALTER TABLE maillane_connection ALTER COLUMN procedure_id CHAR(36) NOT NULL');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        if (self::RESTRICT_TO_PROJECT !== $this->container->getParameter('project_folder')){
            return;
        }
        $this->abortIfNotMysql();

        $matchingRecords = $this->getMatchingRecordsToDown();
        $this->addSql('ALTER TABLE _procedure ADD maillane_connection_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE _procedure ADD CONSTRAINT FK_D1A01D02AC0C069A FOREIGN KEY (maillane_connection_id) REFERENCES maillane_connection (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D1A01D02AC0C069A ON _procedure (maillane_connection_id)');
        foreach ($matchingRecords as $matchingRecord) {
            $this->addSql('UPDATE _procedure SET maillane_connection_id = :maillaneConnectionId
                        WHERE _p_id = :procedureId',
                [
                    'procedureId' => $matchingRecord['procedure_id'],
                    'maillaneConnectionId' => $matchingRecord['id']
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
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
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
