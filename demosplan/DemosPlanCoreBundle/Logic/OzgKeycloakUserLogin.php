<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\OzgKeycloakAuthenticator;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakResponseValueObject;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use demosplan\DemosPlanUserBundle\Logic\OrgaService;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use demosplan\DemosPlanUserBundle\Repository\DepartmentRepository;
use demosplan\DemosPlanUserBundle\Repository\OrgaRepository;
use demosplan\DemosPlanUserBundle\Repository\OrgaTypeRepository;
use demosplan\DemosPlanUserBundle\Repository\UserRepository;
use demosplan\DemosPlanUserBundle\Repository\UserRoleInCustomerRepository;

/**
 * Supposed to handle the request from @link OzgKeycloakAuthenticator to log in a user. Therefore, the information from
 * keycloak will be passed by @link OzgKeycloakResponseValueObject.
 */
class OzgKeycloakUserLogin
{
    private CustomerService $customerService;
    private DepartmentRepository $departmentRepository;
    private EntityManagerInterface $entityManager;
    private GlobalConfig $globalConfig;
    private LoggerInterface $logger;
    private OrgaRepository $orgaRepository;
    private OrgaService $orgaService;
    private OrgaTypeRepository $orgaTypeRepository;
    private OzgKeycloakResponseValueObject $ozgKeycloakResponseValueObject;
    private RoleRepository $roleRepository;
    private UserRepository $userRepository;
    private UserRoleInCustomerRepository $userRoleInCustomerRepository;
    private UserService $userService;
    private ValidatorInterface $validator;
    private const ROLETITLE_TO_ROLECODE = [
        // 'Mandanten-Administration'          => Role::ORGANISATION_ADMINISTRATION,
        'Organisationsadministration'       => Role::ORGANISATION_ADMINISTRATION,
        'Fachplanung-Planungsbüro'          => Role::PRIVATE_PLANNING_AGENCY,
        // 'Verfahrens-Planungsbüro'           => Role::PRIVATE_PLANNING_AGENCY,
        'Fachplanung-Administration'        => Role::PLANNING_AGENCY_ADMIN,
        // 'Verfahrensmanager'                 => Role::PLANNING_AGENCY_ADMIN,
        'Fachplanung-Sachbearbeitung'       => Role::PLANNING_AGENCY_WORKER,
        // 'Verfahrens-Sachbearbeitung'        => Role::PLANNING_AGENCY_WORKER,
        'Institutions-Koordination'         => Role::PUBLIC_AGENCY_COORDINATION,
        'Institutions-Sachbearbeitung'      => Role::PUBLIC_AGENCY_WORKER,
        'Support'                           => Role::PLATFORM_SUPPORT,
        'Plattform-Administration'          => Role::CUSTOMER_MASTER_USER,
        'Redaktion'                         => Role::CONTENT_EDITOR,
        'Privatperson/Angemeldet'           => Role::CITIZEN,
        'Fachliche Leitstelle'              => Role::PROCEDURE_CONTROL_UNIT,
    ];

    public function __construct(
        CustomerService $customerService,
        DepartmentRepository $departmentRepository,
        EntityManagerInterface $entityManager,
        GlobalConfig $globalConfig,
        LoggerInterface $logger,
        OrgaRepository $orgaRepository,
        OrgaService $orgaService,
        OrgaTypeRepository $orgaTypeRepository,
        RoleRepository $roleRepository,
        UserRepository $userRepository,
        UserRoleInCustomerRepository $userRoleInCustomerRepository,
        UserService $userService,
        ValidatorInterface $validator
    ) {
        $this->customerService = $customerService;
        $this->departmentRepository = $departmentRepository;
        $this->entityManager = $entityManager;
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->orgaRepository = $orgaRepository;
        $this->orgaService = $orgaService;
        $this->orgaTypeRepository = $orgaTypeRepository;
        $this->roleRepository = $roleRepository;
        $this->userRepository = $userRepository;
        $this->userRoleInCustomerRepository = $userRoleInCustomerRepository;
        $this->userService = $userService;
        $this->validator = $validator;
    }

    /**
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    public function handleKeycloakData(OzgKeycloakResponseValueObject $ozgKeycloakResponseValueObject): User
    {
        $this->ozgKeycloakResponseValueObject = $ozgKeycloakResponseValueObject;
        // 1 get Desired Roles
        $requestedRoles = $this->mapKeycloakRoleNamesToDplanRoles();
        // 2 handle Organisation / load it / update it / create it --- handle special case CITIZEN
        $requestedOrga = $this->getOrgaAndHandleRequestedOrgaData($requestedRoles);
        // 3 handle user / load it / update it / create it / and add User to Orga and Department

        $existingUser = $this->fetchExistingUser();

        if ($existingUser) {
            // Update user information from keycloak
            $existingUser = $this->updateExistingDplanUser($existingUser, $requestedOrga, $requestedRoles);
            $this->entityManager->getConnection()->commit();

            return $existingUser;
        }

        // 4) Create new User using keycloak data
        $newUser = $this->tryCreateNewUser($requestedOrga, $requestedRoles);
        $this->entityManager->getConnection()->commit();

        return $newUser;
    }

    /**
     * @param array<int, Role> $requestedRoles
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    private function getOrgaAndHandleRequestedOrgaData(array $requestedRoles): Orga
    {
        $existingUser = $this->fetchExistingUser();
        // try to find an existing Organisation that matches the given data (preferably gwId or otherwise name)
        $existingOrga = $this->tryLookupOrgaByGwId();

        // At this point we handle users that have the role CITIZEN within their requested roles.
        // Or the desired Orga ist the CITIZEN orga.
        // CITIZEN are special as they have to be put in their specific organisation
        if ($this->isUserCitizen($requestedRoles)
            || ($existingOrga && User::ANONYMOUS_USER_ORGA_ID === $existingOrga->getId())
        ) {
            // was the user in a different Organisation beforehand - get him out of there and reset his department
            // except it was the CITIZEN organisation already.
            if ($existingUser && !$this->isCurrentlyInCitizenOrga($existingUser)) {
                $this->detachUserFromOrgaAndDepartment($existingUser);
            }
            // just return the CITIZEN organisation and do not update the orga in this case
            // - regardless of other requested roles or an desired update of the orgaName....

            return $this->getCitizenOrga();
        }

        // in case an organisation could be found using the given organisation attributes
        // and an existing user could be found using the given user attributes:
        // If the organisations are different - the assumption is that the user wants to change the orga.
        $moveUserToAnotherOrganisation = $existingUser && $existingOrga && $existingUser->getOrga()
            && $existingUser->getOrga() !== $existingOrga;

        if ($moveUserToAnotherOrganisation) {
            $this->detachUserFromOrgaAndDepartment($existingUser);
        }

        if ($existingOrga) {
            return $this->updateOrganisation($existingOrga, $requestedRoles);
        }

        // if no organisation was found - a new organisation will be created for this user.
        // if the user does exist already, disconnect him from his orga/department
        if ($existingUser && $existingUser->getOrga()) {
            $this->detachUserFromOrgaAndDepartment($existingUser);
        }

        return $this->createNewOrganisation($requestedRoles);
    }

    private function isCurrentlyInCitizenOrga(User $user): bool
    {
        $orga = $user->getOrga();

        return $orga && User::ANONYMOUS_USER_ORGA_ID === $orga->getId();
    }

    /**
     * @throws Exception
     */
    private function detachUserFromOrgaAndDepartment(User $existingUser): void
    {
        $oldOrga = $existingUser->getOrga();
        if ($oldOrga) {
            // get user out of his old organisation
            $oldOrga->removeUser($existingUser);
            $this->entityManager->persist($oldOrga);
            $this->entityManager->persist($existingUser);
        }
        // and his old department
        $this->departmentRepository->removeUser($existingUser->getDepartmentId(), $existingUser);
    }

    /**
     * @param array<int, Role> $requstedRoles
     *
     * @throws CustomerNotFoundException
     */
    private function updateOrganisation(Orga $existingOrga, array $requstedRoles): Orga
    {
        $existingOrga->setDeleted(false);
        // add Customer if not set already
        $customer = $this->customerService->getCurrentCustomer();
        $existingOrga->addCustomer($customer);
        $existingOrga->setGwId($this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId());
        /*
         * This check prevents the case that someone tries to change the orga name to
         * @link User::ANONYMOUS_USER_ORGA_NAME. This name has to stay unique for the Citizen Orga.
         */
        if (User::ANONYMOUS_USER_ORGA_NAME !== $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger()) {
            $existingOrga->setName($this->ozgKeycloakResponseValueObject->getVerfahrenstraeger());
        }
        // what OrgaTypes are needed to be set and accepted regarding the requested Roles?
        $orgaTypesNeededToBeAccepted = $this->getOrgaTypesToSetupRequestedRoles($requstedRoles);
        // are the desired OrgaTypes present and accepted for this organisation/customer
        $currentOrgaStatuses = $existingOrga->getStatusInCustomers()->filter(
            fn (OrgaStatusInCustomer $orgaStatusInCustomer): bool => $orgaStatusInCustomer->getCustomer() === $customer
        );
        foreach ($orgaTypesNeededToBeAccepted as $neededOrgaType) {
            $typeExists = false;
            /** @var OrgaStatusInCustomer $orgaStatusInCurrentCustomer */
            foreach ($currentOrgaStatuses as $orgaStatusInCurrentCustomer) {
                if ($orgaStatusInCurrentCustomer->getOrgaType()->getName() === $neededOrgaType) {
                    $orgaStatusInCurrentCustomer->setStatus(OrgaStatusInCustomer::STATUS_ACCEPTED);
                    $this->entityManager->persist($orgaStatusInCurrentCustomer);
                    $typeExists = true;
                }
            }
            if (!$typeExists) {
                $orgaTypeToAdd = $this->orgaTypeRepository->findOneBy(['name' => $neededOrgaType]);
                if (!$orgaTypeToAdd instanceof OrgaType) {
                    throw new AuthenticationException(
                        'needed OrgaType could not be loaded and therefore cant be added'
                    );
                }
                $existingOrga->addCustomerAndOrgaType($customer, $orgaTypeToAdd);
            }
        }
        $this->entityManager->persist($existingOrga);
        $this->entityManager->flush();

        $this->logger->info(
            'Organisation updated',
            [
                'OrgaName'           => $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger(),
                'gwId'               => $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId(),
                'customer'           => $customer->getName(),
                'requestedOrgaTypes' => $orgaTypesNeededToBeAccepted,
                'newOrgaId'          => $existingOrga->getId(),
            ]
        );

        return $existingOrga;
    }

    /**
     * @param array<int, Role> $requestedRoles
     *
     * @throws AuthenticationException
     * @throws Exception
     */
    private function createNewOrganisation(array $requestedRoles): Orga
    {
        $customer = $this->customerService->getCurrentCustomer();
        $registrationStatuses = [];
        foreach ($this->getOrgaTypesToSetupRequestedRoles($requestedRoles) as $orgaType) {
            $registrationStatuses[] = [
                'status'            => OrgaStatusInCustomer::STATUS_ACCEPTED,
                'subdomain'         => $customer->getSubdomain(),
                'customer'          => $customer,
                'type'              => $orgaType,
            ];
        }
        // set default OrgaType if no role matches to at least register orga in customer.
        // Otherwise, even support could not manage orga afterwards
        if ([] === $registrationStatuses) {
            $registrationStatuses[] = [
                'status'    => OrgaStatusInCustomer::STATUS_ACCEPTED,
                'subdomain' => $customer->getSubdomain(),
                'customer'  => $customer,
                'type'      => OrgaType::DEFAULT,
            ];
        }

        $department = new Department();
        $department->setName(Department::DEFAULT_DEPARTMENT_NAME);
        $this->entityManager->persist($department);
        if (User::ANONYMOUS_USER_ORGA_NAME === $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger()) {
            throw new AuthenticationException('The Organisation name is reserved for citizen!');
        }

        $orgaData = [
            'customer'                  => $this->customerService->getCurrentCustomer(),
            'name'                      => $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger(),
            'registrationStatuses'      => $registrationStatuses,
        ];
        if ('' !== $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId()) {
            // if we get this value set it
            $orgaData['gwId'] = $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId();
        }

        $orga = $this->orgaService->addOrga($orgaData);
        $orga->setDepartments([$department]);
        $this->entityManager->persist($orga);

        $this->logger->info(
            'Organisation hinzugefügt',
            [
                'orgaData'    => $orgaData,
                'newOrgaId'   => $orga->getId(),
            ]
        );

        return $orga;
    }

    /**
     * @param array<int, Role> $requestedRoles
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    private function tryCreateNewUser(Orga $userOrga, array $requestedRoles): ?User
    {
        // if the user should be moved to the CITIZEN orga, the CITIZEN role is the only one allowed
        if (User::ANONYMOUS_USER_ORGA_ID === $userOrga->getId()) {
            $requestedRoles = [$this->roleRepository->findOneBy(['code' => Role::CITIZEN])];
        }

        $userData = [
            'lastname'      => $this->ozgKeycloakResponseValueObject->getVollerName(),
            'email'         => $this->ozgKeycloakResponseValueObject->getEmailAdresse(),
            'login'         => $this->ozgKeycloakResponseValueObject->getNutzerId(),
            'gwId'          => $this->ozgKeycloakResponseValueObject->getProviderId(),
            'customer'      => $this->customerService->getCurrentCustomer(),
            'organisation'  => $userOrga,
            'department'    => $this->getDepartmentToSetForUser($userOrga),
            'roles'         => $requestedRoles,
        ];

        $newUser = $this->userService->addUser($userData);
        $this->logger->info(
            'New user was created.',
            [
                'id'           => $newUser->getId(),
                'userData'     => $newUser->getLastname(),
                'email'        => $newUser->getEmail(),
                'login'        => $newUser->getLogin(),
                'gwId'         => $newUser->getGwId(),
                'customer'     => $newUser->getCurrentCustomer(),
                'organisation' => $newUser->getOrga(),
                'department'   => $newUser->getDepartment(),
                'roles'        => $newUser->getRoles(),
            ]
        );

        return $newUser;
    }

    /**
     * @param array<int, Role> $desiredRoles
     */
    private function isUserCitizen(array $desiredRoles): bool
    {
        foreach ($desiredRoles as $role) {
            if (Role::CITIZEN === $role->getCode()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, Role> $requestedRoles
     *
     * @return array<int, string>
     */
    private function getOrgaTypesToSetupRequestedRoles(array $requestedRoles): array
    {
        $orgaTypesNeeded = [];
        foreach ($requestedRoles as $requestedRole) {
            foreach (OrgaType::ORGATYPE_ROLE as $orgaType => $type) {
                if (in_array($requestedRole->getCode(), $type, true)
                    && !in_array($orgaType, $orgaTypesNeeded, true)
                ) {
                    $orgaTypesNeeded[] = $orgaType;
                }
            }
        }

        return $orgaTypesNeeded;
    }

    /**
     * @return array<int, Role>
     *
     * @throws AuthenticationCredentialsNotFoundException
     */
    private function mapKeycloakRoleNamesToDplanRoles(): array
    {
        $desiredRoleNames = $this->ozgKeycloakResponseValueObject->getRolleDiPlanBeteiligung();
        $recognizedRoleCodes = [];
        $unIdentifiedRoles = [];
        // If we received partially recognizable roles - we try to ignore the garbage data...
        // ['Fachplanung-Administration', 'Sachplanung-Fachbearbeitung', ''] counts as ['Fachplanung-Administration']
        foreach ($desiredRoleNames as $desiredRoleName) {
            if (array_key_exists($desiredRoleName, self::ROLETITLE_TO_ROLECODE)) {
                $recognizedRoleCodes[] = self::ROLETITLE_TO_ROLECODE[$desiredRoleName];
            } else {
                $unIdentifiedRoles[] = $desiredRoleName;
            }
        }
        if (0 !== count($unIdentifiedRoles)) {
            $this->logger->info('at least one non recognizable role was requested!', $unIdentifiedRoles);
        }
        $requestedRoles = $this->filterNonAvailableRolesInProject($recognizedRoleCodes);
        if (0 === count($requestedRoles)) {
            throw new AuthenticationCredentialsNotFoundException('no roles could be identified');
        }

        return $requestedRoles;
    }

    /**
     * @param array<int, string> $requestedRoleCodes
     *
     * @return array<int, Role>
     */
    private function filterNonAvailableRolesInProject(array $requestedRoleCodes): array
    {
        $unavailableRoles = [];
        $availableRequestedRoles = [];
        foreach ($requestedRoleCodes as $roleCode) {
            if (in_array($roleCode, $this->globalConfig->getRolesAllowed(), true)) {
                $availableRequestedRoles[] = $this->roleRepository->findOneBy(['code' => $roleCode]);
            } else {
                $unavailableRoles[] = $this->roleRepository->findOneBy(['code' => $roleCode]);
            }
        }
        if (0 !== count($unavailableRoles)) {
            $this->logger->info('the following requested roles are not available in project', $unavailableRoles);
        }

        return $availableRequestedRoles;
    }

    private function getCitizenOrga(): ?Orga
    {
        return $this->orgaRepository->findOneBy(['id' => User::ANONYMOUS_USER_ORGA_ID]);
    }

    private function tryLookupOrgaByGwId(): ?Orga
    {
        $orga = null;
        if ('' !== $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId()) {
            $orga = $this->orgaRepository
                ->findOneBy(['gwId' => $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId()]);
        }

        return $orga;
    }

    /**
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    private function updateExistingDplanUser(User $dplanUser, Orga $orga, array $requestedRoles): User
    {
        // if the user should be moved to the CITIZEN orga, the CITIZEN role is the only one allowed
        if (User::ANONYMOUS_USER_ORGA_ID === $orga->getId()) {
            $requestedRoles = [$this->roleRepository->findOneBy(['code' => Role::CITIZEN])];
        }

        if ($this->hasUserAttributeToUpdate($dplanUser->getDplanroles()->toArray(), $requestedRoles)) {
            $customer = $this->customerService->getCurrentCustomer();
            $customerId = $customer->getId();
            $userId = $dplanUser->getId();
            // To update the user roles we clear them first and set the roles from keycloak.
            $this->userRoleInCustomerRepository->clearUserRoles($userId, $customerId);
            $dplanUser->clearRolesCache();
            // refresh $user manually after tampering with relations
            $this->entityManager->refresh($dplanUser);

            $dplanUser->setDplanroles($requestedRoles, $customer);
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getGwId(),
            $this->ozgKeycloakResponseValueObject->getProviderId()
        )) {
            $dplanUser->setGwId($this->ozgKeycloakResponseValueObject->getProviderId());
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getLogin(),
            $this->ozgKeycloakResponseValueObject->getNutzerId()
        )) {
            $dplanUser->setLogin($this->ozgKeycloakResponseValueObject->getNutzerId());
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getEmail(),
            $this->ozgKeycloakResponseValueObject->getEmailAdresse()
        )) {
            $dplanUser->setEmail($this->ozgKeycloakResponseValueObject->getEmailAdresse());
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getFullname(),
            $this->ozgKeycloakResponseValueObject->getVollerName()
        )) {
            $dplanUser->setFirstname('');
            $dplanUser->setLastname($this->ozgKeycloakResponseValueObject->getVollerName());
        }

        $this->orgaService->orgaAddUser($orga->getId(), $dplanUser);
        $departmentToSet = $this->getDepartmentToSetForUser($orga);
        if ($dplanUser->getDepartment() !== $departmentToSet) {
            $this->userService->departmentAddUser($departmentToSet->getId(), $dplanUser);
        }
        $violations = new ConstraintViolationList([]);
        $violations->addAll($this->validator->validate($dplanUser));
        $violations->addAll($this->validator->validate($orga));
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $this->entityManager->persist($dplanUser);
        $this->entityManager->flush();

        $this->logger->info(
            'Existing user was updated.',
            [
                'id'           => $dplanUser->getId(),
                'lastname'     => $dplanUser->getLastname(),
                'email'        => $dplanUser->getEmail(),
                'login'        => $dplanUser->getLogin(),
                'gwId'         => $dplanUser->getGwId(),
                'customer'     => $dplanUser->getCurrentCustomer(),
                'organisation' => $dplanUser->getOrga(),
                'department'   => $dplanUser->getDepartment(),
                'roles'        => $dplanUser->getRoles(),
            ]
        );

        return $dplanUser;
    }

    private function getDepartmentToSetForUser(Orga $userOrga): Department
    {
        return $userOrga->getDepartments()->filter(
            static fn (Department $department): bool => Department::DEFAULT_DEPARTMENT_NAME === $department->getName()
        )->first() ?? $userOrga->getDepartments()->first();
    }

    private function hasUserAttributeToUpdate($dplanUserAttribute, $keycloakUserAttribute): bool
    {
        /*
         * At this point, two arrays should be checked for the same content.
         * The order of the content should be irrelevant, because it cannot
         * be guaranteed that the roles coming from Keycloak are in the same
         * order as those from Dplan.
         */
        if (is_array($dplanUserAttribute) && is_array($keycloakUserAttribute)) {
            if (count($dplanUserAttribute) !== count($keycloakUserAttribute)) {
                return true;
            }

            return !empty(array_diff($keycloakUserAttribute, $dplanUserAttribute));
        }

        return $dplanUserAttribute !== $keycloakUserAttribute;
    }

    private function fetchExistingUser(): ?User
    {
        // 1) have they logged in with Keycloak before? Easy!
        $existingUser = $this->fetchExistingUserViaGatewayId();
        if (null === $existingUser) {
            // 2) do we have a matching user by login
            $existingUser = $this->fetchExistingUserViaLoginAttribute();
        }
        if (null === $existingUser) {
            // 3) do we have a matching user by email?
            $existingUser = $this->fetchExistingUserViaEmail();
        }

        return $existingUser;
    }

    private function fetchExistingUserViaGatewayId(): ?User
    {
        return $this->userRepository->findOneBy(['gwId' => $this->ozgKeycloakResponseValueObject->getProviderId()]);
    }

    private function fetchExistingUserViaLoginAttribute(): ?User
    {
        return $this->userRepository->findOneBy(['login' => $this->ozgKeycloakResponseValueObject->getNutzerId()]);
    }

    private function fetchExistingUserViaEmail(): ?User
    {
        return $this->userRepository->findOneBy(['email' => $this->ozgKeycloakResponseValueObject->getEmailAdresse()]);
    }
}
