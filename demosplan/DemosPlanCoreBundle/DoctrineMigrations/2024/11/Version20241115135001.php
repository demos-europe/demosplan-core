<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

class Version20241115135001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initially fill _st_text_raw';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // read all statements text and store them in _st_text_raw surrounded by <pre> tags
        $prefix = sprintf('<dplan-statement><dplan-segment data-segment-id="%s">', Uuid::uuid4());
        $suffix = '</dplan-segment></dplan-statement>';
        $this->addSql(sprintf("UPDATE _statement SET _st_text_raw = CONCAT('%s', _st_text, '%s')", $prefix, $suffix));

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('UPDATE _statement SET _st_text_raw = :empty', ['empty' => '']);

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
