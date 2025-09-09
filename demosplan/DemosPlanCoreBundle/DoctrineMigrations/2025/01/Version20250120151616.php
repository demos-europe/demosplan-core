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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250120151616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DPLAN-14787: Remove duplicate role entry for planning agency admin';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Identify the duplicate role entries
        $duplicateRoles = $this->connection->fetchAllAssociative('
            SELECT _r_id
            FROM _role
            WHERE _r_code = :code
        ', ['code' => RoleInterface::PLANNING_AGENCY_ADMIN]);

        if (2 !== count($duplicateRoles)) {
            return;
        }

        // Assuming the IDs of the duplicate roles are 1 and 2
        $roleIdToKeep = $duplicateRoles[0]['_r_id'];
        $roleIdToRemove = $duplicateRoles[1]['_r_id'];

        // fetch all entries with the old id, try to update them one by one, catch
        // non-unique constraint violations and delete the entry if it could not be updated
        $this->updateRoleReferences('_news_roles', '_r_id', '_n_id', $roleIdToRemove, $roleIdToKeep);
        $this->updateRoleReferences('_platform_content_roles', '_r_id', '_pc_id', $roleIdToRemove, $roleIdToKeep);
        $this->updateRoleReferences('access_control', 'role_id', 'id', $roleIdToRemove, $roleIdToKeep);
        $this->updateRoleReferences('faq_role', 'role_id', 'faq_id', $roleIdToRemove, $roleIdToKeep);
        $this->updateRoleReferences('platform_faq_role', 'role_id', 'platformFaq_id', $roleIdToRemove, $roleIdToKeep);
        $this->updateRoleReferences('relation_role_user_customer', 'role', 'id', $roleIdToRemove, $roleIdToKeep);

        // Delete the duplicate role entry
        $this->addSql('DELETE FROM `_role` WHERE _r_id = :roleIdToRemove', ['roleIdToRemove' => $roleIdToRemove]);
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        // revert is not possible
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

    private function updateRoleReferences(string $table, string $roleColumn, string $idColumn, mixed $roleIdToRemove, mixed $roleIdToKeep): void
    {
        $entries = $this->connection->fetchAllAssociative(
            "SELECT * FROM `$table` WHERE $roleColumn = :roleIdToRemove",
            ['roleIdToRemove' => $roleIdToRemove]
        );

        foreach ($entries as $entry) {
            try {
                $this->connection->executeStatement(
                    "UPDATE `$table` SET $roleColumn = :roleIdToKeep WHERE $idColumn = :id",
                    ['roleIdToKeep' => $roleIdToKeep, 'id' => $entry[$idColumn]]
                );
            } catch (Exception) {
                // Entries are deleted later on in bulk
            }
        }

        $this->addSql(
            "DELETE FROM `$table` WHERE $roleColumn = :roleIdToRemove",
            ['roleIdToRemove' => $roleIdToRemove]
        );
    }
}
