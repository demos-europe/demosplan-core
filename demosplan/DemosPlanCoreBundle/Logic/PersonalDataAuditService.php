<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\PersonalDataAuditLog;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Repository\PersonalDataAuditRepository;

readonly class PersonalDataAuditService
{
    public function __construct(
        private PersonalDataAuditRepository $repository,
        private PermissionsInterface $permissions,
    ) {
    }

    /**
     * @return array<int, PersonalDataAuditLog>
     *
     * @throws AccessDeniedException
     */
    public function getAuditLogByEntityId(string $entityId, int $limit = 1000): array
    {
        $this->assertViewPermission();

        return $this->repository->getChangesByEntityId($entityId, $limit);
    }

    /**
     * @return array<int, PersonalDataAuditLog>
     *
     * @throws AccessDeniedException
     */
    public function getAuditLogByUserId(string $userId, int $limit = 1000): array
    {
        $this->assertViewPermission();

        return $this->repository->getChangesByUserId($userId, $limit);
    }

    /**
     * @return array<int, PersonalDataAuditLog>
     *
     * @throws AccessDeniedException
     */
    public function getAuditLogByEntityType(string $entityType, ?DateTime $from = null, ?DateTime $to = null, int $limit = 1000): array
    {
        $this->assertViewPermission();

        return $this->repository->getChangesByEntityType($entityType, $from, $to, $limit);
    }

    /**
     * Anonymize user name in audit log entries for GDPR data removal.
     * The audit entries themselves are preserved to document that changes occurred.
     */
    public function anonymizeUserName(string $userId, string $anonymizedName): int
    {
        return $this->repository->anonymizeUserName($userId, $anonymizedName);
    }

    public function deleteByEntityId(string $entityId): int
    {
        return $this->repository->deleteByEntityId($entityId);
    }

    private function assertViewPermission(): void
    {
        if (!$this->permissions->hasPermission('feature_personal_data_audit_view')) {
            throw new AccessDeniedException('Permission feature_personal_data_audit_view required');
        }
    }
}
