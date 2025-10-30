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
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DepartmentMapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger)
    {
    }

    // Detect department fromy Keycloak token and assign to user
    public function assignUserDepartmentFromToken(User $user, Orga $orga, string $departmentNameInToken): void
    {
        $currentDepartment = $user->getDepartment();

        // Check if current department name matches token
        if (null !== $currentDepartment && $currentDepartment instanceof Department && $departmentNameInToken === $currentDepartment->getName()) {
            return;
        }

        // If no department in ozgKeycloak token, use default
        if (empty($departmentNameInToken)) {
            $departmentToSet = $this->getDefaultDepartment($orga);
            $this->updateUserDeparment($user, $departmentToSet);

            return;
        }

        // Find or create department
        $departmentToSet = $this->findOrCreateDepartment($orga, $departmentNameInToken);
        $this->updateUserDeparment($user, $departmentToSet);
    }

    private function updateUserDeparment(User $user, Department $departmentToSet): void
    {
        if ($departmentToSet !== $user->getDepartment()) {
            $this->removeDepartmentFromUser($user);
            $departmentToSet->addUser($user);
        }
    }

    public function findOrCreateDepartment(Orga $orga, string $departmentNameInToken): Department
    {
        // If no organisational unit is provided, use default department
        if (empty($departmentNameInToken)) {
            return $this->getDefaultDepartment($orga);
        }

        // Try to find existing department with this name in the organisation
        $existingDepartment = $orga->getDepartments()->filter(
            fn (Department $dept): bool => $dept->getName() === $departmentNameInToken
                && !$dept->isDeleted()
        )->first();

        if (null !== $existingDepartment) {
            return $existingDepartment;
        }

        return $this->createDepartmentAndAddToOrga($orga, $departmentNameInToken);
    }

    private function createDepartmentAndAddToOrga(Orga $orga, string $departmentNameInToken): Department
    {
        $newDepartment = new Department();
        $newDepartment->setName($departmentNameInToken);
        $newDepartment->addOrga($orga);
        $this->entityManager->persist($newDepartment);

        // Add to organisation's departments
        $orga->addDepartment($newDepartment);
        $this->entityManager->persist($orga);
        $this->entityManager->flush();

        $this->logger->info('Created new department for organisational unit',
            [
                'departmentName' => $departmentNameInToken,
                'orgaName'       => $orga->getName(),
                'orgaId'         => $orga->getId(),
            ]);

        return $newDepartment;
    }

    private function getDefaultDepartment(Orga $userOrga): Department
    {
        return $userOrga->getDepartments()->filter(
            static fn (Department $department): bool => Department::DEFAULT_DEPARTMENT_NAME === $department->getName()
        )->first() ?? $userOrga->getDepartments()->first();
    }

    private function removeDepartmentFromUser(User $user): void
    {
        $originalDepartment = $user->getDepartment();
        if ($originalDepartment instanceof Department) {
            $originalDepartment->setGwId(null);
            $originalDepartment->removeUser($user);
        }
    }
}
