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
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DepartmentMapper
{
    public function __construct(private readonly OzgKeycloakUserData $ozgKeycloakUserData,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserService $userService,
        private readonly LoggerInterface $logger)
    {
    }

    // Sync department on subsequent logins
    public function assingUserDepartmentFromToken(User $user, Orga $orga): Department
    {
        $departmentInToken = $this->ozgKeycloakUserData->getCompanyDepartment();
        $currentDepartment = $user->getDepartment();

        // If no department in ozgKeycloak token, keep current or use default
        if (empty($departmentInToken)) {
            return $this->getDepartmentToSetForUser($orga);
        }

        // Check if current department name matches token
        if ($currentDepartment && $currentDepartment->getName() ===
            $departmentInToken) {
            return $currentDepartment;
        }

        $this->removeDeparmentFromUser($user);

        // Find or create department
        return $this->findOrCreateDepartment($orga);
    }

    public function findOrCreateDepartment(Orga $orga): Department
    {
        $departmentNameInToken = $this->ozgKeycloakUserData->getCompanyDepartment();

        // If no organisational unit is provided, use default department
        if (empty($departmentNameInToken)) {
            return $this->getDepartmentToSetForUser($orga);
        }

        // Try to find existing department with this name in the organisation
        $existingDepartment = $orga->getDepartments()->filter(
            fn (Department $dept): bool => $dept->getName() === $departmentNameInToken
                && !$dept->isDeleted()
        )->first();

        if ($existingDepartment) {
            return $existingDepartment;
        }

        return $this->createDeparmentAndAddToOrga($orga, $departmentNameInToken);
    }

    private function createDeparmentAndAddToOrga(Orga $orga, string $departmentNameInToken): Department
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

    private function getDepartmentToSetForUser(Orga $userOrga): Department
    {
        return $userOrga->getDepartments()->filter(
            static fn (Department $department): bool => Department::DEFAULT_DEPARTMENT_NAME === $department->getName()
        )->first() ?? $userOrga->getDepartments()->first();
    }

    private function removeDeparmentFromUser(User $user): void
    {
        $originalDepartment = $user->getDepartment();
        if ($originalDepartment instanceof Department) {
            $originalDepartment->setGwId(null);
            $originalDepartment->removeUser($user);
        }
    }

    public function storeNewDeparmentToUser(Department $departmentToSet, User $dplanUser): void
    {
        /** @var DepartmentRepository $departmentRepos */
        $departmentRepos = $this->entityManager->getRepository(Department::class);
        $departmentRepos->addUser(
            $departmentToSet->getId(),
            $dplanUser);
        // $this->userService->departmentAddUser($departmentToSet->getId(), $dplanUser);
        $this->entityManager->refresh($dplanUser);
    }
}
