<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DepartmentMapper
{
    public function __construct(private readonly OzgKeycloakUserData $ozgKeycloakUserData,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger)
    {
    }

    public function findOrCreateDepartment(Orga $orga): Department
    {
        $orgUnitName = $this->ozgKeycloakUserData->getCompanyDepartment();

        // If no organisational unit is provided, use default department
        if (empty($orgUnitName)) {
            return $this->getDepartmentToSetForUser($orga);
        }

        // Try to find existing department with this name in the organisation
        $existingDepartment = $orga->getDepartments()->filter(
            fn (Department $dept): bool => $dept->getName() === $orgUnitName
                && !$dept->isDeleted()
        )->first();

        if ($existingDepartment) {
            return $existingDepartment;
        }

        // Create new department
        $newDepartment = new Department();
        $newDepartment->setName($orgUnitName);
        $newDepartment->addOrga($orga);
        $this->entityManager->persist($newDepartment);

        // Add to organisation's departments
        $orga->addDepartment($newDepartment);
        $this->entityManager->persist($orga);

        $this->logger->info('Created new department for organisational unit',
            [
                'departmentName' => $orgUnitName,
                'orgaName'       => $orga->getName(),
                'orgaId'         => $orga->getId(),
            ]);

        return $newDepartment;
    }

    private function getDepartmentToSetForUser(Orga $userOrga): Department
    {
        return $userOrga->getDepartments()->filter(
            static fn (Department $department): bool => Department::DEFAULT_DEPARTMENT_NAME === $department->getName()
        )->first() ?? $userOrga->getDepartments()->first();
    }

    // Sync department on subsequent logins
    public function syncUserDepartmentFromToken(User $user, Orga $orga): Department
    {
        $departmentInToken = $this->ozgKeycloakUserData->getCompanyDepartment();
        $currentDepartment = $user->getDepartment();

        // If no department in ozgKeycloak token, keep current or use default
        if (empty($departmentInToken)) {
            return $currentDepartment ??
                $this->getDepartmentToSetForUser($orga);
        }

        // Check if current department name matches token
        if ($currentDepartment && $currentDepartment->getName() ===
            $departmentInToken) {
            return $currentDepartment;
        }

        // Find or create department
        $targetDepartment = $this->findOrCreateDepartment($orga);

        // If user needs to be moved to different department
        if (!$currentDepartment || $currentDepartment->getId() !== $targetDepartment->getId()) {
            if ($currentDepartment) {
                $currentDepartment->removeUser($user);
                $this->entityManager->persist($currentDepartment);
            }

            $targetDepartment->addUser($user);
            $this->entityManager->persist($targetDepartment);
            $this->entityManager->persist($user);

            $this->logger->info('User moved to department from organisational unit', [
                'userId'             => $user->getId(),
                'oldDepartment'      => $currentDepartment?->getName(),
                'newDepartment'      => $targetDepartment->getName(),
                'organisationalUnit' => $departmentInToken,
            ]);
        }

        return $targetDepartment;
    }
}
