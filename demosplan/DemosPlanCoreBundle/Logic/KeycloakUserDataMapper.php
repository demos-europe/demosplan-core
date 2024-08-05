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

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\User\NewOrgaRegisteredEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserData;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Supposed to handle the request from @see KeycloakAuthenticator to log in a user. Therefore, the information from
 * keycloak will be passed by @see KeycloakUserData.
 */
class KeycloakUserDataMapper
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly EventDispatcherPostInterface $eventDispatcherPost,
        private readonly LoggerInterface $logger,
        private readonly OrgaService $orgaService,
        private readonly RoleHandler $roleHandler,
        private readonly UserService $userService
    ) {
    }

    /**
     * Maps incoming data to dplan:user.
     *
     * @throws Exception
     */
    public function mapUserData(KeycloakUserData $keycloakUserData): UserInterface
    {
        // login existing user
        $user = $this->userService->findDistinctUserByEmailOrLogin($keycloakUserData->getEmailAddress());

        if ($user instanceof User) {
            $this->logger->info('Found user in Keycloak request', ['data' => $keycloakUserData]);

            $this->updateOrgaWithKnownValues($user->getOrga(), $keycloakUserData);

            return $this->updateUserWithKnownValues($user, $keycloakUserData);
        }

        $this->logger->info('Eventually create new User from Keycloak', ['data' => $keycloakUserData]);

        // At this state the login attempt may be from different Identity Providers (IdP).
        // In some cases the IdP only passes an orga without any acting user (like Orgakonto Bund)
        // while in other cases it might be a citizen (and always a citizen, like in Nutzerkonto Brandenburg)
        // We need to distinguish the cases here and act accordingly

        // Does the payload carry an organisation?
        if ('' !== $keycloakUserData->getOrganisationName()) {
            $this->logger->info('User is Orgauser');

            return $this->getUserForOrga($keycloakUserData);
        }

        // otherwise we assume that it is a citizen
        $this->logger->info('User is citizen');

        return $this->getUserForNewCitizen($keycloakUserData);
    }

    private function getUserForNewCitizen(KeycloakUserData $keycloakUserData): User
    {
        $login = $keycloakUserData->getUserName();

        $this->logger->info('Create new User from Keycloak data');

        // user does not yet exist
        $user = $this->getNewUserWithDefaultValues();
        $user->setEmail($keycloakUserData->getEmailAddress());
        $user->setFirstname($keycloakUserData->getFirstName());
        $user->setLastname($keycloakUserData->getLastName());
        $user->setLogin($login);

        $roleCodes = [RoleInterface::CITIZEN];
        $user = $this->addUserRoles($roleCodes, $user);
        $anonymousUser = new AnonymousUser();

        return $this->addUserToOrgaAndDepartment($anonymousUser->getOrga(), $user);
    }

    private function getUserForOrga(KeycloakUserData $keycloakUserData): UserInterface
    {
        // in this context the given Id is the GatewayName of the organisation
        $orgas = $this->orgaService->getOrgaByFields(['gwId' => $keycloakUserData->getOrganisationId()]);
        if (null === $orgas || (is_array($orgas) && empty($orgas))) {
            $this->logger->info('Create new User and Orga from Keycloak');

            return $this->createNewUserAndOrgaFromOrga($keycloakUserData);
        }

        // orga is already registered in customer, return default user

        $this->logger->info('Orga is already registered in customer');
        $orga = $orgas[0] ?? null;
        if (!$orga instanceof Orga) {
            $this->logger->error('Could not find valid orga in Keycloak request', [$orgas]);
            throw new InvalidArgumentException('Could not find valid orga in Keycloak request');
        }

        $this->logger->info('Update existing Orga with Keycloak data');
        $orga = $this->updateOrgaWithKnownValues($orga, $keycloakUserData);

        $users = $orga->getUsers();

        // return the orga default user
        $user = $users->filter(fn (User $user) => UserInterface::DEFAULT_ORGA_USER_NAME === $user->getLastname());

        $this->logger->info('Fetched users', ['users' => $user]);

        if (1 === $user->count()) {
            return $user->first();
        }

        $this->logger->error('No valid users found');

        throw new InvalidArgumentException('Invalid Keycloak user attributes given');
    }

    private function getNewUserWithDefaultValues(): User
    {
        $user = new User();
        $user->setAccessConfirmed(true);
        $user->setAlternativeLoginPassword('loginViaKeycloak');
        $user->setForumNotification(false);
        $user->setInvited(false);
        $user->setNewsletter(false);
        $user->setNewUser(false);
        $user->setNoPiwik(false);
        $user->setPassword('loginViaKeycloak');
        $user->setProfileCompleted(true);
        $user->setProvidedByIdentityProvider(true);

        return $user;
    }

    /**
     * We could not find any existing orga, so we need to create a new orga
     * with one default user.
     */
    private function createNewUserAndOrgaFromOrga(KeycloakUserData $keycloakUserData): UserInterface
    {
        $customer = $this->customerService->getCurrentCustomer();

        $orgaName = $keycloakUserData->getOrganisationName();
        $phone = '';
        // we need to hardcode the initial user name here to be able to
        // login as the default Orga user on login
        $userFirstName = '';
        $userLastName = UserInterface::DEFAULT_ORGA_USER_NAME;
        $userEmail = $keycloakUserData->getEmailAddress();
        $orgaTypeNames = [OrgaTypeInterface::PUBLIC_AGENCY];

        $orga = $this->orgaService->createOrgaRegister(
            $orgaName,
            $phone,
            $userFirstName,
            $userLastName,
            $userEmail,
            $customer,
            $orgaTypeNames
        );

        $orga = $this->updateOrgaWithKnownValues($orga, $keycloakUserData);

        try {
            $newOrgaRegisteredEvent = new NewOrgaRegisteredEvent(
                $userEmail,
                $orgaTypeNames,
                $customer->getName(),
                $userFirstName,
                $userLastName,
                $orgaName
            );
            $this->eventDispatcherPost->post($newOrgaRegisteredEvent);
        } catch (Exception $e) {
            $this->logger->warning('Could not successfully perform orga registered from OAuth login event', [$e]);
        }

        // Orga has exactly one master user so far
        $user = $orga->getUsers()->first();
        // set Orga Email as User login to be able to login as the default Orga user on login
        $user->setLogin($keycloakUserData->getUserName());
        $user->setProvidedByIdentityProvider(true);

        return $this->userService->updateUserObject($user);
    }

    private function addUserRoles(array $roleCodes, User $user): User
    {
        $roles = $this->roleHandler->getUserRolesByCodes($roleCodes);
        $role = $roles[0];
        $customer = $this->customerService->getCurrentCustomer();
        $user->setDplanroles([$role], $customer);
        $user->setCurrentCustomer($customer);

        return $user;
    }

    private function addUserToOrgaAndDepartment(?Orga $orga, User $user): User
    {
        // add user to citizen orga and department
        if ($orga instanceof Orga) {
            /** @var Department $department */
            $department = $orga->getDepartments()->first();
            $user->setOrga($orga);
            $user->setDepartment($department);

            $orga->addUser($user);
            $department->addUser($user);
        }

        return $user;
    }

    private function updateOrgaWithKnownValues(Orga $orga, KeycloakUserData $keycloakUserData): Orga
    {
        $orga->setPostalcode($keycloakUserData->getPostalCode());
        $orga->setCity($keycloakUserData->getLocalityName());
        $orga->setStreet($keycloakUserData->getStreet());
        $orga->setHouseNumber($keycloakUserData->getHouseNumber());
        $orga->setGatewayName($keycloakUserData->getOrganisationId());
        $orga->setGwId($keycloakUserData->getOrganisationId());

        return $this->orgaService->updateOrga($orga);
    }

    private function updateUserWithKnownValues(User $user, KeycloakUserData $keycloakUserData): User
    {
        $user->setEmail($keycloakUserData->getEmailAddress());
        $user->setFirstname($keycloakUserData->getFirstName());
        $user->setLastname($keycloakUserData->getLastName());
        // do not update login, as it is used to identify the user

        return $this->userService->updateUserObject($user);
    }
}
