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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\DepartmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper\DepartmentMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRoleInCustomerRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\OzgKeycloakAuthenticator;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserDataInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Supposed to handle the request from @see OzgKeycloakAuthenticator to log in a user. Therefore, the information from
 * keycloak will be passed by @see OzgKeycloakUserData.
 */
class OzgKeycloakUserDataMapper
{
    private KeycloakUserDataInterface $ozgKeycloakUserData;

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly DepartmentRepository $departmentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly OrgaRepository $orgaRepository,
        private readonly OrgaService $orgaService,
        private readonly OrgaTypeRepository $orgaTypeRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserRepository $userRepository,
        private readonly UserRoleInCustomerRepository $userRoleInCustomerRepository,
        private readonly UserService $userService,
        private readonly ValidatorInterface $validator,
        private readonly DepartmentMapper $departmentMapper,
        private readonly OzgKeycloakGroupBasedRoleMapper $groupBasedRoleMapper,
    ) {
    }

    /**
     * Maps incoming data to dplan:user.
     * Supports both single organisation and multi-organisation tokens.
     * Multi-organisation is determined by the cartesian product of affiliations × responsibilities.
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    public function mapUserData(KeycloakUserDataInterface $ozgKeycloakUserData): User
    {
        $this->ozgKeycloakUserData = $ozgKeycloakUserData;
        $requestedRoles = $this->mapUserRoleData();

        // Check if this is a multi-organisation token (affiliations × responsibilities)
        if ($ozgKeycloakUserData instanceof OzgKeycloakUserData) {
            $entries = $this->buildOrganisationEntries(
                $ozgKeycloakUserData->getAffiliations(),
                $ozgKeycloakUserData->getResponsibilities()
            );

            if (count($entries) > 1) {
                return $this->mapMultiOrganisationUser($entries, $requestedRoles);
            }

            if (1 === count($entries)) {
                // Single org from token arrays — use it instead of organisationId
                return $this->mapSingleEntryOrganisationUser($entries[0], $requestedRoles);
            }
        }

        // No entries from token arrays → existing single-org flow using organisationId
        $requestedOrganisation = $this->mapUserOrganisationData($requestedRoles);
        $existingUser = $this->tryToFindExistingUser();

        if ($existingUser instanceof User) {
            return $this->updateExistingDplanUser($existingUser, $requestedOrganisation, $requestedRoles);
        }

        return $this->createNewUser($requestedOrganisation, $requestedRoles);
    }

    /**
     * Compute the cartesian product of affiliations × responsibilities.
     * Organisation (affiliations) is always >= 1, responsibilities is 0..n.
     *
     * Fallback rules:
     * 1. Both present → cartesian product (gwId = aff.id + '.' + resp.id)
     * 2. Only affiliations (no responsibilities) → use affiliations alone
     * 3. No affiliations → empty array (caller falls back to organisationId)
     *
     * @param array<int, array{id: string, name: string}> $affiliations
     * @param array<int, array{id: string, name: string}> $responsibilities
     *
     * @return array<int, array{gwId: string, name: string}>
     */
    private function buildOrganisationEntries(array $affiliations, array $responsibilities): array
    {
        if ([] === $affiliations) {
            return [];
        }

        // Affiliations + responsibilities → cartesian product
        if ([] !== $responsibilities) {
            $entries = [];
            foreach ($affiliations as $aff) {
                foreach ($responsibilities as $resp) {
                    $entries[] = [
                        'gwId' => $aff['id'].'.'.$resp['id'],
                        'name' => $aff['name'].' - '.$resp['name'],
                    ];
                }
            }

            return $entries;
        }

        // Affiliations only (no responsibilities)
        return array_map(static fn (array $a): array => ['gwId' => $a['id'], 'name' => $a['name']], $affiliations);
    }

    /**
     * Handle a single organisation entry from token arrays.
     * Routes through the normal org lookup/create flow using the entry's gwId and name.
     *
     * @param array{gwId: string, name: string} $entry
     * @param array<int, Role>                  $requestedRoles
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    private function mapSingleEntryOrganisationUser(array $entry, array $requestedRoles): User
    {
        $existingUser = $this->tryToFindExistingUser();

        // Handle private persons / citizens
        if ($this->ozgKeycloakUserData->isPrivatePerson() || $this->isUserCitizen($requestedRoles)) {
            $citizenOrga = $this->getCitizenOrga();
            if ($existingUser instanceof User) {
                return $this->updateExistingDplanUser($existingUser, $citizenOrga, $requestedRoles);
            }

            return $this->createNewUser($citizenOrga, $requestedRoles);
        }

        $orga = $this->orgaRepository->findOneBy(['gwId' => $entry['gwId']]);

        if ($orga instanceof Orga) {
            $orga = $this->updateOrganisation($orga, $requestedRoles, $entry['gwId'], $entry['name']);
        } else {
            $orga = $this->createNewOrganisation($requestedRoles, $entry['gwId'], $entry['name']);
        }

        if ($existingUser instanceof User) {
            $user = $this->updateExistingDplanUser($existingUser, $orga, $requestedRoles);
            // Sync: remove stale org links from previous multi-org tokens
            $this->syncUserOrganisations($user, [$orga]);

            return $user;
        }

        return $this->createNewUser($orga, $requestedRoles);
    }

    /**
     * Handle multi-organisation user mapping.
     * Links user to all organisations from the cartesian product of affiliations × responsibilities.
     *
     * @param array<int, array{gwId: string, name: string}> $entries
     * @param array<int, Role>                              $requestedRoles
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    private function mapMultiOrganisationUser(array $entries, array $requestedRoles): User
    {
        $existingUser = $this->tryToFindExistingUser();

        // Handle private persons (citizens) - they don't get multi-org
        if ($this->ozgKeycloakUserData->isPrivatePerson() || $this->isUserCitizen($requestedRoles)) {
            $citizenOrga = $this->getCitizenOrga();
            if ($existingUser instanceof User) {
                return $this->updateExistingDplanUser($existingUser, $citizenOrga, $requestedRoles);
            }

            return $this->createNewUser($citizenOrga, $requestedRoles);
        }

        // Find or create all organisations from entries
        $organisations = $this->findOrCreateOrganisationsFromToken($entries, $requestedRoles);

        if ([] === $organisations) {
            throw new AuthenticationException('No valid organisations could be created from token');
        }

        // Use first organisation as primary (for backward compatibility)
        $primaryOrga = $organisations[0];

        if ($existingUser instanceof User) {
            $user = $this->updateExistingDplanUser($existingUser, $primaryOrga, $requestedRoles);
        } else {
            $user = $this->createNewUser($primaryOrga, $requestedRoles);
        }

        // Sync user's org links: add new ones, remove stale ones
        $this->syncUserOrganisations($user, $organisations);

        $this->logger->info('Multi-organisation user mapped', [
            'userId' => $user->getId(),
            'organisationCount' => count($organisations),
            'organisations' => array_map(fn (Orga $o) => $o->getId(), $organisations),
        ]);

        return $user;
    }

    /**
     * Find or create organisations from token entries (cartesian product results).
     *
     * @param array<int, array{gwId: string, name: string}> $entries
     * @param array<int, Role>                              $requestedRoles
     *
     * @return array<int, Orga>
     *
     * @throws CustomerNotFoundException
     */
    private function findOrCreateOrganisationsFromToken(array $entries, array $requestedRoles): array
    {
        $organisations = [];

        foreach ($entries as $entry) {
            $gwId = $entry['gwId'];
            $orgaName = $entry['name'];

            // Skip citizen organisation identifier
            if (UserInterface::ANONYMOUS_USER_ORGA_ID === $gwId || UserInterface::ANONYMOUS_USER_ORGA_NAME === $orgaName) {
                continue;
            }

            $orga = $this->orgaRepository->findOneBy(['gwId' => $gwId]);

            if ($orga instanceof Orga) {
                $organisations[] = $this->updateOrganisation($orga, $requestedRoles, $gwId, $orgaName);
            } else {
                $organisations[] = $this->createNewOrganisation($requestedRoles, $gwId, $orgaName);
            }
        }

        return $organisations;
    }

    /**
     * Sync user's organisation links to match the given target set.
     * Adds missing links and removes stale ones no longer present in the token.
     *
     * @param array<int, Orga> $targetOrganisations
     */
    private function syncUserOrganisations(User $user, array $targetOrganisations): void
    {
        $targetIds = array_map(static fn (Orga $o): string => $o->getId(), $targetOrganisations);

        // Remove stale org links not in target set
        // Use unlinkUser/removeOrganisation to avoid setOrga()/unsetOrgas() side effects
        foreach ($user->getOrganisations()->toArray() as $currentOrga) {
            if (!in_array($currentOrga->getId(), $targetIds, true)) {
                $user->removeOrganisation($currentOrga);
                $currentOrga->unlinkUser($user);
                $this->entityManager->persist($currentOrga);
            }
        }

        // Add missing org links
        // Use linkUser/addOrganisation to avoid setOrga() overwriting the user's org collection
        foreach ($targetOrganisations as $orga) {
            if (!$user->getOrganisations()->contains($orga)) {
                $user->addOrganisation($orga);
                $orga->linkUser($user);
                $this->entityManager->persist($orga);
            }
        }

        $this->entityManager->persist($user);
    }

    /**
     * Creates a new organisation in case of incoming organisation could not match with existing organisations.
     * In case of incoming organisation can be found, it will be updated with incoming data.
     * Also handles the special case of citizen organisation.
     *
     * @param array<int, Role> $requestedRoles
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    private function mapUserOrganisationData(array $requestedRoles): Orga
    {
        $existingUser = $this->tryToFindExistingUser();
        // try to find an existing Organisation that matches the given data (preferably gwId or otherwise name)
        $existingOrga = $this->tryLookupOrgaByGwId();

        // At this point we handle users that are private persons (citizens).
        // Check the isPrivatePerson attribute from the token first, then fall back to role-based detection.
        // Or the desired Orga ist the CITIZEN orga.
        // CITIZEN are special as they have to be put in their specific organisation
        $isPrivatePersonByAttribute = $this->ozgKeycloakUserData->isPrivatePerson();
        $isPrivatePersonByRole = $this->isUserCitizen($requestedRoles);
        $isPrivatePersonByOrga = $existingOrga instanceof Orga && UserInterface::ANONYMOUS_USER_ORGA_ID === $existingOrga->getId();

        if ($isPrivatePersonByAttribute) {
            $this->logger->info('User identified as private person via isPrivatePerson token attribute');
        }

        if ($isPrivatePersonByAttribute || $isPrivatePersonByRole || $isPrivatePersonByOrga) {
            // was the user in a different Organisation beforehand - get him out of there and reset his department
            // except it was the CITIZEN organisation already.
            if ($existingUser instanceof User && !$this->isCurrentlyInCitizenOrga($existingUser)) {
                $this->detachUserFromOrgaAndDepartment($existingUser);
            }
            // just return the CITIZEN organisation and do not update the orga in this case
            // - regardless of other requested roles or an desired update of the orgaName....

            return $this->getCitizenOrga();
        }

        // in case an organisation could be found using the given organisation attributes
        // and an existing user could be found using the given user attributes:
        // If the organisations are different - the assumption is that the user wants to change the orga.
        $moveUserToAnotherOrganisation =
            $existingUser instanceof User
            && $existingOrga instanceof Orga
            && $existingUser->getOrga() instanceof OrgaInterface
            && $existingUser->getOrga()->getId() !== $existingOrga->getId();

        if ($moveUserToAnotherOrganisation) {
            $this->detachUserFromOrgaAndDepartment($existingUser);
        }

        if ($existingOrga instanceof Orga) {
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
        if ($oldOrga instanceof OrgaInterface) {
            // get user out of his old organisation
            $oldOrga->removeUser($existingUser);
            $this->entityManager->persist($oldOrga);
            $this->entityManager->persist($existingUser);
        }
        // and his old department
        $this->departmentRepository->removeUser($existingUser->getDepartmentId(), $existingUser);
    }

    /**
     * Update an existing organisation with customer and orga types.
     *
     * @param array<int, Role> $requestedRoles
     * @param string|null      $gwId     Override gwId (for multi-organisation), null = use token value + address
     * @param string|null      $orgaName Override name (for multi-organisation), null = use token value
     *
     * @throws CustomerNotFoundException
     */
    private function updateOrganisation(Orga $existingOrga, array $requestedRoles, ?string $gwId = null, ?string $orgaName = null): Orga
    {
        $customer = $this->customerService->getCurrentCustomer();

        $gwId ??= $this->ozgKeycloakUserData->getOrganisationId();
        $orgaName ??= $this->ozgKeycloakUserData->getOrganisationName();

        $existingOrga->setDeleted(false);
        $existingOrga->addCustomer($customer);

        if ('' !== $gwId) {
            $existingOrga->setGwId($gwId);
        }

        // Do not overwrite org name on update — FPA users can modify it via the UI.
        // Name is only set on org creation. Address fields are still synced from the token.
        if (UserInterface::ANONYMOUS_USER_ORGA_NAME !== $orgaName) {
            $existingOrga->setStreet($this->ozgKeycloakUserData->getStreet());
            $existingOrga->setAddressExtension($this->ozgKeycloakUserData->getAddressExtension());
            $existingOrga->setHouseNumber($this->ozgKeycloakUserData->getHouseNumber());
            $existingOrga->setPostalcode($this->ozgKeycloakUserData->getPostalCode());
            $existingOrga->setCity($this->ozgKeycloakUserData->getCity());
        }

        $this->ensureOrgaTypesForRoles($existingOrga, $customer, $requestedRoles);
        $this->entityManager->persist($existingOrga);

        $this->logger->info('Organisation updated', [
            'orgaId' => $existingOrga->getId(),
            'gwId' => $gwId,
            'name' => $orgaName,
        ]);

        return $existingOrga;
    }

    /**
     * Ensure organisation has required orga types for the requested roles.
     *
     * @param array<int, Role> $requestedRoles
     */
    private function ensureOrgaTypesForRoles(Orga $orga, CustomerInterface $customer, array $requestedRoles): void
    {
        $orgaTypesNeeded = $this->getOrgaTypesToSetupRequestedRoles($requestedRoles);
        $currentOrgaStatuses = $orga->getStatusInCustomers()->filter(
            fn (OrgaStatusInCustomer $status): bool => $status->getCustomer() === $customer
        );

        foreach ($orgaTypesNeeded as $neededOrgaType) {
            $typeExists = false;
            foreach ($currentOrgaStatuses as $orgaStatus) {
                if ($orgaStatus->getOrgaType()->getName() === $neededOrgaType) {
                    $orgaStatus->setStatus(OrgaStatusInCustomerInterface::STATUS_ACCEPTED);
                    $this->entityManager->persist($orgaStatus);
                    $typeExists = true;
                }
            }
            if (!$typeExists) {
                $orgaTypeToAdd = $this->orgaTypeRepository->findOneBy(['name' => $neededOrgaType]);
                if ($orgaTypeToAdd instanceof OrgaType) {
                    $orga->addCustomerAndOrgaType($customer, $orgaTypeToAdd);
                }
            }
        }
    }

    /**
     * Create a new organisation.
     *
     * @param array<int, Role> $requestedRoles
     * @param string|null      $gwId     Override gwId (for multi-organisation), defaults to token value
     * @param string|null      $orgaName Override name (for multi-organisation), defaults to token value
     *
     * @throws AuthenticationException
     * @throws Exception
     */
    private function createNewOrganisation(array $requestedRoles, ?string $gwId = null, ?string $orgaName = null): Orga
    {
        $customer = $this->customerService->getCurrentCustomer();
        $gwId ??= $this->ozgKeycloakUserData->getOrganisationId();
        $orgaName ??= $this->ozgKeycloakUserData->getOrganisationName();

        if (UserInterface::ANONYMOUS_USER_ORGA_NAME === $orgaName) {
            throw new AuthenticationException('The Organisation name is reserved for citizen!');
        }

        $registrationStatuses = $this->buildRegistrationStatuses($customer, $requestedRoles);

        $department = new Department();
        $department->setName(DepartmentInterface::DEFAULT_DEPARTMENT_NAME);
        $this->entityManager->persist($department);

        $orgaData = [
            'customer' => $customer,
            'name' => $orgaName,
            'registrationStatuses' => $registrationStatuses,
        ];

        if ('' !== $gwId) {
            $orgaData['gwId'] = $gwId;
        }

        $orga = $this->orgaService->addOrga($orgaData);
        $orga->setDepartments([$department]);
        $this->entityManager->persist($orga);

        $this->logger->info('Organisation created', [
            'orgaId' => $orga->getId(),
            'gwId' => $gwId,
            'name' => $orgaName,
        ]);

        return $orga;
    }

    /**
     * Build registration statuses for a new organisation.
     *
     * @param array<int, Role> $requestedRoles
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRegistrationStatuses(CustomerInterface $customer, array $requestedRoles): array
    {
        $statuses = [];
        foreach ($this->getOrgaTypesToSetupRequestedRoles($requestedRoles) as $orgaType) {
            $statuses[] = [
                'status' => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
                'subdomain' => $customer->getSubdomain(),
                'customer' => $customer,
                'type' => $orgaType,
            ];
        }

        // Default OrgaType if no role matches
        if ([] === $statuses) {
            $statuses[] = [
                'status' => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
                'subdomain' => $customer->getSubdomain(),
                'customer' => $customer,
                'type' => OrgaTypeInterface::DEFAULT,
            ];
        }

        return $statuses;
    }

    /**
     * @param array<int, Role> $requestedRoles
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    private function createNewUser(Orga $userOrga, array $requestedRoles): ?User
    {
        // if the user should be moved to the CITIZEN orga, the CITIZEN role is the only one allowed
        if (UserInterface::ANONYMOUS_USER_ORGA_ID === $userOrga->getId()) {
            $requestedRoles = [$this->roleRepository->findOneBy(['code' => RoleInterface::CITIZEN])];
        }

        $userData = [
            'firstname'     => $this->ozgKeycloakUserData->getFirstName(),
            'lastname'      => $this->ozgKeycloakUserData->getLastName(),
            'email'         => $this->ozgKeycloakUserData->getEmailAddress(),
            'login'         => $this->ozgKeycloakUserData->getUserName(),
            'gwId'          => $this->ozgKeycloakUserData->getUserId(),
            'customer'      => $this->customerService->getCurrentCustomer(),
            'organisation'  => $userOrga,
            'department'    => $this->departmentMapper->findOrCreateDepartment($userOrga, $this->ozgKeycloakUserData->getCompanyDepartment()),
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
            foreach (OrgaTypeInterface::ORGATYPE_ROLE as $orgaType => $type) {
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
     * Map related roles of data stored in this->ozgKeycloakUserData.
     *
     * @return array<int, Role>
     *
     * @throws AuthenticationCredentialsNotFoundException
     */
    private function mapUserRoleData(): array
    {
        // Special handling for private persons - automatically assign CITIZEN role
        if ($this->ozgKeycloakUserData->isPrivatePerson()) {
            return $this->getCitizenRoleForPrivatePerson();
        }

        $rolesOfCustomer = $this->ozgKeycloakUserData->getCustomerRoleRelations();
        $customer = $this->customerService->getCurrentCustomer();

        return $this->groupBasedRoleMapper->mapGroupBasedRoles(
            $rolesOfCustomer,
            $customer->getSubdomain()
        );
    }

    /**
     * Get CITIZEN role for private persons.
     *
     * @return array<int, Role>
     *
     * @throws AuthenticationCredentialsNotFoundException
     */
    private function getCitizenRoleForPrivatePerson(): array
    {
        $this->logger->info('Private person detected - automatically assigning CITIZEN role');
        $citizenRole = $this->roleRepository->findOneBy(['code' => Role::CITIZEN]);
        if (null === $citizenRole) {
            throw new AuthenticationCredentialsNotFoundException('CITIZEN role not found in system');
        }

        return [$citizenRole];
    }

    private function getCitizenOrga(): ?Orga
    {
        return $this->orgaRepository->findOneBy(['id' => User::ANONYMOUS_USER_ORGA_ID]);
    }

    private function tryLookupOrgaByGwId(): ?Orga
    {
        $organisation = null;
        if ('' !== $this->ozgKeycloakUserData->getOrganisationId()) {
            $organisation = $this->orgaRepository
                ->findOneBy(['gwId' => $this->ozgKeycloakUserData->getOrganisationId()]);
        }

        return $organisation;
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
            $this->ozgKeycloakUserData->getUserId()
        )) {
            $dplanUser->setGwId($this->ozgKeycloakUserData->getUserId());
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getLogin(),
            $this->ozgKeycloakUserData->getUserName()
        )) {
            $dplanUser->setLogin($this->ozgKeycloakUserData->getUserName());
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getEmail(),
            $this->ozgKeycloakUserData->getEmailAddress()
        )) {
            $dplanUser->setEmail($this->ozgKeycloakUserData->getEmailAddress());
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getFirstname(),
            $this->ozgKeycloakUserData->getFirstName()
        )) {
            $dplanUser->setFirstname($this->ozgKeycloakUserData->getFirstName());
        }

        if ($this->hasUserAttributeToUpdate(
            $dplanUser->getLastname(),
            $this->ozgKeycloakUserData->getLastName()
        )) {
            $dplanUser->setLastname($this->ozgKeycloakUserData->getLastName());
        }

        $this->orgaService->orgaAddUser($orga->getId(), $dplanUser);

        $this->departmentMapper->assignUserDepartmentFromToken($dplanUser, $orga, $this->ozgKeycloakUserData->getCompanyDepartment());

        $violations = new ConstraintViolationList([]);
        $violations->addAll($this->validator->validate($dplanUser));
        $violations->addAll($this->validator->validate($orga));
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        // user is provided by the identity provider
        $dplanUser->setProvidedByIdentityProvider(true);

        $this->entityManager->persist($dplanUser);
        // Removed flush() call - let the main transaction handle persistence

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

            return [] !== array_diff($keycloakUserAttribute, $dplanUserAttribute);
        }

        return $dplanUserAttribute !== $keycloakUserAttribute;
    }

    /**
     * Try to find a existing user based on the following data in the named order.´:
     * - gatewayId
     * - login
     * - email
     * Will return the user in case of user was found, otherwise null.
     */
    private function tryToFindExistingUser(): ?User
    {
        // 1) have they logged in with Keycloak before? Easy!
        $existingUser = $this->fetchExistingUserViaGatewayId();
        if (!$existingUser instanceof User) {
            // 2) do we have a matching user by login
            $existingUser = $this->fetchExistingUserViaLoginAttribute();
        }
        if (!$existingUser instanceof User) {
            // 3) do we have a matching user by email?
            $existingUser = $this->fetchExistingUserViaEmail();
        }

        return $existingUser;
    }

    private function fetchExistingUserViaGatewayId(): ?User
    {
        return $this->userRepository->findOneBy(['gwId' => $this->ozgKeycloakUserData->getUserId()]);
    }

    private function fetchExistingUserViaLoginAttribute(): ?User
    {
        return $this->userRepository->findOneBy(['login' => $this->ozgKeycloakUserData->getUserName()]);
    }

    private function fetchExistingUserViaEmail(): ?User
    {
        return $this->userRepository->findOneBy(['email' => $this->ozgKeycloakUserData->getEmailAddress()]);
    }
}
