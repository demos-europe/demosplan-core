<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260508131628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sortIndex column to _tag for manual ordering of tags within a topic; backfill by createDate ASC.';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _tag ADD _t_sort_index INT DEFAULT 0 NOT NULL');

        // Backfill _tag.sortIndex by createDate ASC, partitioned per topic
        $this->addSql(
            'UPDATE _tag t JOIN ('
            .'SELECT _t_id, ROW_NUMBER() OVER (PARTITION BY _tt_id ORDER BY _t_create_date ASC, _t_id ASC) - 1 AS rn '
            .'FROM _tag'
            .') ranked ON ranked._t_id = t._t_id SET t._t_sort_index = ranked.rn'
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('ALTER TABLE _tag DROP _t_sort_index');
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
