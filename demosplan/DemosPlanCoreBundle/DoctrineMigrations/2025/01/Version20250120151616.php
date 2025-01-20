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
        $this->updateNewsRoles($roleIdToRemove, $roleIdToKeep);
        $this->updatePlatformContentRoles($roleIdToRemove, $roleIdToKeep);
        $this->updateAccessControl($roleIdToRemove, $roleIdToKeep);
        $this->updateFaqRole($roleIdToRemove, $roleIdToKeep);
        $this->updatePlatformFaqRole($roleIdToRemove, $roleIdToKeep);
        $this->updateRelationRoleUserCustomer($roleIdToRemove, $roleIdToKeep);
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

    private function updateNewsRoles(mixed $roleIdToRemove, mixed $roleIdToKeep): void
    {
        $entries = $this->connection->fetchAllAssociative(
            'SELECT *
                FROM `_news_roles`
                WHERE _r_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
        foreach ($entries as $entry) {
            try {
                $this->connection->executeStatement(
                    'UPDATE `_news_roles` SET _r_id = :roleIdToKeep WHERE _n_id = :id',
                    ['roleIdToKeep' => $roleIdToKeep, 'id' => $entry['_n_id']]
                );
            } catch (Exception) {
                // Entries are deleted later on in bulk
            }
        }

        $this->addSql(
            'DELETE FROM `_news_roles` WHERE _r_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
    }

    private function updatePlatformContentRoles(mixed $roleIdToRemove, mixed $roleIdToKeep): void
    {
        $entries = $this->connection->fetchAllAssociative(
            'SELECT *
                FROM `_platform_content_roles`
                WHERE _r_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
        foreach ($entries as $entry) {
            try {
                $this->connection->executeStatement(
                    'UPDATE `_platform_content_roles` SET _r_id = :roleIdToKeep WHERE _pc_id = :id',
                    ['roleIdToKeep' => $roleIdToKeep, 'id' => $entry['_pc_id']]
                );
            } catch (Exception) {
                // Entries are deleted later on in bulk
            }
        }
        $this->addSql(
            'DELETE FROM `_platform_content_roles` WHERE _r_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
    }

    private function updateAccessControl(mixed $roleIdToRemove, mixed $roleIdToKeep): void
    {
        $entries = $this->connection->fetchAllAssociative(
            'SELECT *
                FROM `access_control`
                WHERE role_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
        foreach ($entries as $entry) {
            try {
                $this->connection->executeStatement(
                    'UPDATE `access_control` SET role_id = :roleIdToKeep WHERE id = :id',
                    ['roleIdToKeep' => $roleIdToKeep, 'id' => $entry['id']]
                );
            } catch (Exception) {
                // Entries are deleted later on in bulk
            }
        }
        $this->addSql(
            'DELETE FROM `access_control` WHERE role_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
    }

    private function updateFaqRole(mixed $roleIdToRemove, mixed $roleIdToKeep): void
    {
        $entries = $this->connection->fetchAllAssociative(
            'SELECT *
                FROM `faq_role`
                WHERE role_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
        foreach ($entries as $entry) {
            try {
                $this->connection->executeStatement(
                    'UPDATE `faq_role` SET role_id = :roleIdToKeep WHERE faq_id = :id',
                    ['roleIdToKeep' => $roleIdToKeep, 'id' => $entry['faq_id']]
                );
            } catch (Exception) {
                // Entries are deleted later on in bulk
            }
        }
        $this->addSql(
            'DELETE FROM `faq_role` WHERE role_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
    }

    private function updatePlatformFaqRole(mixed $roleIdToRemove, mixed $roleIdToKeep): void
    {
        $entries = $this->connection->fetchAllAssociative(
            'SELECT *
                FROM `platform_faq_role`
                WHERE role_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
        foreach ($entries as $entry) {
            try {
                $this->connection->executeStatement(
                    'UPDATE `platform_faq_role` SET role_id = :roleIdToKeep WHERE platformFaq_id = :id',
                    [
                        'roleIdToKeep' => $roleIdToKeep,
                        'id'           => $entry['platformFaq_id'],
                    ]
                );
            } catch (Exception) {
                // Entries are deleted later on in bulk
            }
        }
        $this->addSql(
            'DELETE FROM `platform_faq_role` WHERE role_id = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
    }

    private function updateRelationRoleUserCustomer(mixed $roleIdToRemove, mixed $roleIdToKeep): void
    {
        $entries = $this->connection->fetchAllAssociative(
            'SELECT *
            FROM `relation_role_user_customer`
            WHERE role = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
        foreach ($entries as $entry) {
            try {
                $this->connection->executeStatement(
                    'UPDATE `relation_role_user_customer` SET role = :roleIdToKeep WHERE id = :id',
                    ['roleIdToKeep' => $roleIdToKeep, 'id' => $entry['id']]
                );
            } catch (Exception $e) {
                // Entries are deleted later on in bulk
            }
        }
        $this->addSql(
            'DELETE FROM `relation_role_user_customer` WHERE role = :roleIdToRemove',
            ['roleIdToRemove' => $roleIdToRemove]
        );
    }
}
