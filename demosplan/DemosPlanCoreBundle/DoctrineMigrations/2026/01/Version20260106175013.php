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

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260106175013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs DS-397: Clean up invalid access_control permissions that do not match organization types';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Hardcoded ORGATYPE_ROLE mapping from OrgaTypeInterface
        $orgaTypeRoleMapping = [
            'OPSORG' => ['RPSOCO', 'RPSODE'],  // PUBLIC_AGENCY
            'OLAUTH' => ['RMOPSA', 'RMOPSD'],  // MUNICIPALITY
            'OPAUTH' => ['RMOPPO'],            // PLANNING_AGENCY
            'OHAUTH' => ['RMOPHA', 'RMOHAW'],  // HEARING_AUTHORITY_AGENCY
        ];

        $this->write('Starting access_control cleanup...');
        $this->write('');

        // Get all access_control entries
        $allEntries = $this->connection->fetchAllAssociative('SELECT * FROM access_control');
        $totalEntries = count($allEntries);
        $this->write(sprintf('Total entries to check: %d', $totalEntries));
        $this->write('');

        $toDelete = [];
        $deletionReasons = [
            'orga_not_accepted' => [],
            'role_mismatch'     => [],
        ];

        foreach ($allEntries as $entry) {
            $entryId = $entry['id'];
            $orgaId = $entry['orga_id'];
            $customerId = $entry['customer_id'];
            $roleId = $entry['role_id'];
            $permissionName = $entry['permission'];

            // Get orga name for logging
            $orgaName = $this->connection->fetchOne(
                'SELECT _o_name FROM _orga WHERE _o_id = :orgaId',
                ['orgaId' => $orgaId]
            ) ?: 'Unknown';

            // Get customer subdomain for logging
            $customerSubdomain = $this->connection->fetchOne(
                'SELECT _c_subdomain FROM customer WHERE _c_id = :customerId',
                ['customerId' => $customerId]
            ) ?: 'Unknown';

            // Check if orga is accepted in customer
            $acceptedTypes = $this->connection->fetchAllAssociative(
                'SELECT ot._ot_name as name
                 FROM relation_customer_orga_orga_type rcoot
                 JOIN _orga_type ot ON rcoot._ot_id = ot._ot_id
                 WHERE rcoot._o_id = :orgaId
                 AND rcoot._c_id = :customerId
                 AND rcoot.status = :status',
                ['orgaId' => $orgaId, 'customerId' => $customerId, 'status' => 'accepted']
            );

            if (empty($acceptedTypes)) {
                // Orga not accepted in this customer - mark for deletion
                $toDelete[] = $entryId;
                $deletionReasons['orga_not_accepted'][] = [
                    'id'                => $entryId,
                    'orgaId'            => $orgaId,
                    'orgaName'          => $orgaName,
                    'customerId'        => $customerId,
                    'customerSubdomain' => $customerSubdomain,
                    'roleId'            => $roleId,
                    'permission'        => $permissionName,
                ];
                continue;
            }

            // Get role code for this entry
            $roleCode = $this->connection->fetchOne(
                'SELECT _r_code FROM _role WHERE _r_id = :roleId',
                ['roleId' => $roleId]
            );

            if (!$roleCode) {
                // Role doesn't exist - mark for deletion
                $toDelete[] = $entryId;
                $deletionReasons['role_mismatch'][] = [
                    'id'                => $entryId,
                    'orgaId'            => $orgaId,
                    'orgaName'          => $orgaName,
                    'customerId'        => $customerId,
                    'customerSubdomain' => $customerSubdomain,
                    'roleId'            => $roleId,
                    'roleCode'          => 'NOT_FOUND',
                    'permission'        => $permissionName,
                    'acceptedTypes'     => array_column($acceptedTypes, 'name'),
                    'reason'            => 'Role not found in database',
                ];
                continue;
            }

            // Check if role is valid for any of the orga's types
            $roleValid = false;
            $acceptedTypeNames = array_column($acceptedTypes, 'name');

            foreach ($acceptedTypeNames as $typeName) {
                if (isset($orgaTypeRoleMapping[$typeName])) {
                    $allowedRoles = $orgaTypeRoleMapping[$typeName];
                    if (in_array($roleCode, $allowedRoles, true)) {
                        $roleValid = true;
                        break;
                    }
                }
            }

            if (!$roleValid) {
                // Role doesn't match any of the orga's current types - mark for deletion
                $toDelete[] = $entryId;
                $deletionReasons['role_mismatch'][] = [
                    'id'                => $entryId,
                    'orgaId'            => $orgaId,
                    'orgaName'          => $orgaName,
                    'customerId'        => $customerId,
                    'customerSubdomain' => $customerSubdomain,
                    'roleId'            => $roleId,
                    'roleCode'          => $roleCode,
                    'permission'        => $permissionName,
                    'acceptedTypes'     => $acceptedTypeNames,
                    'reason'            => 'Role not allowed for current organization types',
                ];
            }
        }

        // Report findings
        $this->write('=== CLEANUP REPORT ===');
        $this->write('');
        $this->write(sprintf('Entries marked for deletion: %d / %d', count($toDelete), $totalEntries));
        $this->write('');

        if (!empty($deletionReasons['orga_not_accepted'])) {
            $this->write(sprintf('Reason: Organization not accepted in customer (%d entries):', count($deletionReasons['orga_not_accepted'])));
            foreach ($deletionReasons['orga_not_accepted'] as $entry) {
                $this->write(sprintf(
                    '  - ID %s: Orga "%s" (%s) not accepted in customer "%s" - Permission: %s, RoleId: %s',
                    $entry['id'],
                    $entry['orgaName'],
                    $entry['orgaId'],
                    $entry['customerSubdomain'],
                    $entry['permission'],
                    $entry['roleId']
                ));
            }
            $this->write('');
        }

        if (!empty($deletionReasons['role_mismatch'])) {
            $this->write(sprintf('Reason: Role does not match organization types (%d entries):', count($deletionReasons['role_mismatch'])));
            foreach ($deletionReasons['role_mismatch'] as $entry) {
                $this->write(sprintf(
                    '  - ID %s: Orga "%s" (%s) in customer "%s" has types [%s] but entry has role "%s" - Permission: %s - %s',
                    $entry['id'],
                    $entry['orgaName'],
                    $entry['orgaId'],
                    $entry['customerSubdomain'],
                    implode(', ', $entry['acceptedTypes']),
                    $entry['roleCode'],
                    $entry['permission'],
                    $entry['reason']
                ));
            }
            $this->write('');
        }

        // Delete invalid entries
        if (!empty($toDelete)) {
            $this->write('Deleting invalid entries...');

            // Delete in batches to avoid issues with large datasets
            $batchSize = 100;
            $batches = array_chunk($toDelete, $batchSize);

            foreach ($batches as $batch) {
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $this->connection->executeStatement(
                    "DELETE FROM access_control WHERE id IN ($placeholders)",
                    $batch
                );
            }

            $this->write(sprintf('Successfully deleted %d invalid entries', count($toDelete)));
        } else {
            $this->write('No invalid entries found - database is clean!');
        }

        $this->write('');
        $this->write('=== CLEANUP COMPLETED ===');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // This migration cannot be reverted as deleted data cannot be recovered
        $this->write('This migration is irreversible. Deleted permissions cannot be restored.');
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
