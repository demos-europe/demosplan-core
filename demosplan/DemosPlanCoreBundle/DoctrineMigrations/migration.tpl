<?php declare(strict_types = 1);

namespace <namespace>;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CHARSET GUIDANCE: When writing raw SQL CREATE TABLE statements, always use
 * explicit `utf8mb3` charset and `utf8mb3_unicode_ci` collation to match the
 * existing schema. Never use ambiguous `UTF8` — on MariaDB servers without
 * `UTF8_IS_UTF8MB3` in old_mode, it resolves to `utf8mb4`, causing FK mismatches.
 *
 * Example: CREATE TABLE foo (...) DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci ENGINE = InnoDB
 */
class <className> extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T: ';
        PLEASE ADD A DESCRIPTION
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

    <up>
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

    <down>
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
