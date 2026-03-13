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

use DemosEurope\DemosplanAddon\Contracts\Entities\DepartmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\AzureUserData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Azure OAuth user mapper with auto-provisioning support.
 *
 * Lookup order: gwId (Azure oid) → email → create new user.
 * New users are assigned to the default organisation configured in CustomerOAuthConfig.
 */
class AzureUserDataMapper
{
    public function __construct(
        private readonly CustomerOAuthConfigRepository $configRepository,
        private readonly CustomerService $customerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly UserRepository $userRepository,
        private readonly UserService $userService,
    ) {
    }

    /**
     * Finds an existing user or creates a new one from Azure OAuth data.
     *
     * @throws AuthenticationException when user cannot be found or created
     */
    public function mapUserData(AzureUserData $azureUserData): UserInterface
    {
        // 1. Try to find by Azure object ID (stable across email changes)
        $user = $this->findByGwId($azureUserData->getObjectId());
        if ($user instanceof User) {
            $this->logger->info('Found existing user by Azure oid', [
                'email'    => $azureUserData->getEmailAddress(),
                'objectId' => $azureUserData->getObjectId(),
            ]);

            return $user;
        }

        // 2. Try to find by email/login
        $user = $this->userService->findDistinctUserByEmailOrLogin($azureUserData->getEmailAddress());
        if ($user instanceof User) {
            $this->logger->info('Found existing user by email for Azure OAuth', [
                'email'    => $azureUserData->getEmailAddress(),
                'objectId' => $azureUserData->getObjectId(),
            ]);

            // Store Azure oid for future lookups
            if ('' === $user->getGwId()) {
                $user->setGwId($azureUserData->getObjectId());
                $this->entityManager->flush();
            }

            return $user;
        }

        // 3. Auto-provision new user
        return $this->createUser($azureUserData);
    }

    private function findByGwId(string $objectId): ?User
    {
        if ('' === $objectId) {
            return null;
        }

        return $this->userRepository->findOneBy(['gwId' => $objectId, 'deleted' => false]);
    }

    /**
     * @throws AuthenticationException when auto-provisioning is not possible
     */
    private function createUser(AzureUserData $azureUserData): User
    {
        $customer = $this->customerService->getCurrentCustomer();
        $config = $this->configRepository->findByCustomer($customer);

        if (null === $config || null === $config->getDefaultOrganisation()) {
            $this->logger->warning('Azure OAuth auto-provisioning not available: no default organisation configured', [
                'email'    => $azureUserData->getEmailAddress(),
                'objectId' => $azureUserData->getObjectId(),
            ]);

            throw new AuthenticationException(
                'User not found and auto-provisioning is not configured. '
                .'Please contact your administrator.'
            );
        }

        /** @var Orga $organisation */
        $organisation = $config->getDefaultOrganisation();
        $department = $this->getDefaultDepartment($organisation);

        $userData = [
            'email'                       => $azureUserData->getEmailAddress(),
            'firstname'                   => $azureUserData->getFirstName(),
            'gwId'                        => $azureUserData->getObjectId(),
            'lastname'                    => $azureUserData->getLastName(),
            'login'                       => $azureUserData->getEmailAddress(),
            'organisation'                => $organisation,
            'department'                  => $department,
            'providedByIdentityProvider'  => true,
            'roles'                       => [RoleInterface::PLANNING_AGENCY_WORKER],
        ];

        $user = $this->userService->addUser($userData);

        $this->logger->info('Auto-provisioned new user from Azure OAuth', [
            'userId'       => $user->getId(),
            'email'        => $azureUserData->getEmailAddress(),
            'objectId'     => $azureUserData->getObjectId(),
            'organisation' => $organisation->getId(),
        ]);

        return $user;
    }

    private function getDefaultDepartment(Orga $organisation): Department
    {
        $defaultDepartment = $organisation->getDepartments()->filter(
            static fn (Department $department): bool => DepartmentInterface::DEFAULT_DEPARTMENT_NAME === $department->getName()
        )->first();

        if ($defaultDepartment instanceof Department) {
            return $defaultDepartment;
        }

        // Fall back to any department
        $anyDepartment = $organisation->getDepartments()->first();
        if ($anyDepartment instanceof Department) {
            return $anyDepartment;
        }

        // Create default department if none exists
        $department = new Department();
        $department->setName(DepartmentInterface::DEFAULT_DEPARTMENT_NAME);
        $organisation->addDepartment($department);
        $this->entityManager->persist($department);

        return $department;
    }
}
