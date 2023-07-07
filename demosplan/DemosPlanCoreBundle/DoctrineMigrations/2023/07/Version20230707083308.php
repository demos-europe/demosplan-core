<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230707083308 extends AbstractMigration
{
    private const OLD_NAMESPACE = 'demosplan\\DemosPlanCoreBundle';
    private const NEW_NAMESPACE = 'DemosEurope\\Demosplan';

    public function getDescription(): string
    {
        return 'refs T30603: Replaces old namespace for entities in DB with new namespace';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Update entity_content_change
        $this->addSql(
            "UPDATE entity_content_change AS ecc
            SET
                ecc.entity_type = REPLACE(ecc.entity_type, :oldNamespace, :newNamespace)",
            [
                'oldNamespace'        => self::OLD_NAMESPACE,
                'newNamespace'        => self::NEW_NAMESPACE,
            ]
        );

        // Update entity_sync_link
        $this->addSql(
            "UPDATE entity_sync_link AS esl
            SET
                esl.class = REPLACE(esl.class, :oldNamespace, :newNamespace)",
            [
                'oldNamespace'        => self::OLD_NAMESPACE,
                'newNamespace'        => self::NEW_NAMESPACE,
            ]
        );

        // Update file_container
        $this->addSql(
            "UPDATE file_container AS fc
            SET
                fc.entity_class = REPLACE(fc.entity_class, :oldNamespace, :newNamespace)",
            [
                'oldNamespace'        => self::OLD_NAMESPACE,
                'newNamespace'        => self::NEW_NAMESPACE,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Downgrade entity_content_change
        $this->addSql(
            "UPDATE entity_content_change AS ecc
            SET
                ecc.entity_type = REPLACE(ecc.entity_type, :newNamespace, :oldNamespace)",
            [
                'oldNamespace'        => self::OLD_NAMESPACE,
                'newNamespace'        => self::NEW_NAMESPACE,
            ]
        );

        // Downgrade file_container
        $this->addSql(
            "UPDATE entity_sync_link AS esl
            SET
                esl.class = REPLACE(esl.class, :newNamespace, :oldNamespace)",
            [
                'oldNamespace'        => self::OLD_NAMESPACE,
                'newNamespace'        => self::NEW_NAMESPACE,
            ]
        );

        // Downgrade entity_content_change
        $this->addSql(
            "UPDATE file_container AS fc
            SET
                fc.entity_class = REPLACE(fc.entity_class, :newNamespace, :oldNamespace)",
            [
                'oldNamespace'        => self::OLD_NAMESPACE,
                'newNamespace'        => self::NEW_NAMESPACE,
            ]
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
