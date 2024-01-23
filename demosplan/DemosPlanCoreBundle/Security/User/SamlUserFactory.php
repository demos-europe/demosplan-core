<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\User\NewOrgaRegisteredEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Exception;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;
use Hslavich\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SamlUserFactory implements SamlUserFactoryInterface
{
    public function __construct(private readonly CustomerService $customerService, private readonly EventDispatcherPostInterface $eventDispatcherPost, private readonly LoggerInterface $logger, private readonly OrgaService $orgaService, private readonly RoleHandler $roleHandler, private readonly UserService $userService)
    {
    }

    public function createUser($username, array $attributes = []): UserInterface
    {
        $this->logger->info('Eventually create new User from SAML', ['attributes' => $attributes]);

        if ($username instanceof SamlTokenInterface) {
            trigger_deprecation('hslavich/oneloginsaml-bundle', '2.1', 'Usage of %s is deprecated.', SamlTokenInterface::class);

            [$username, $attributes] = [$username->getUserIdentifier(), $username->getAttributes()];
        }

        // At this state the login attempt may be from different Identity Providers (IdP).
        // In some cases the IdP only passes an orga without any acting user (like Orgakonto Bund)
        // while in other cases it might be a citizen (and always a citizen, like in Nutzerkonto Brandenburg)
        // We need to distinguish the cases here and act accordingly

        // Does the payload carry an organisation?
        if (array_key_exists('orgaName', $attributes) && '' !== ($attributes['orgaName'][0] ?? '')) {
            return $this->getUserForOrga($username, $attributes);
        }

        // otherwise we assume that it is a citizen
        return $this->getUserForNewCitizen($attributes);
    }

    private function getUserForNewCitizen(array $attributes): User
    {
        $login = $attributes['ID'][0] ?? '';

        // check whether existing user needs to be updated.
        // when user with email exists, update login field to saml login
        // to allow Login via saml and dplan

        $user = $this->userService->findDistinctUserByEmailOrLogin($attributes['email'][0] ?? '');

        if ($user instanceof User) {
            $this->logger->info('Tie existing user to SAML-Login');
            // user exists with email. Just update login to tie user to saml and at the same time
            // allow to login locally via email
            $user->setLogin($login);
            $user->setProvidedByIdentityProvider(true);

            return $this->userService->updateUserObject($user);
        }

        $this->logger->info('Create new User from SAML data');

        // user does not yet exist
        $user = $this->getNewUserWithDefaultValues();
        $user->setEmail($attributes['email'][0] ?? '');
        $user->setFirstname($attributes['surname'][0] ?? '');
        $user->setLastname($attributes['givenName'][0] ?? '');
        $user->setLogin($login);

        $roleCodes = [Role::CITIZEN];
        $user = $this->addUserRoles($roleCodes, $user);
        $anonymousUser = $this->userService->getValidUser(User::ANONYMOUS_USER_LOGIN);

        return $this->addUserToOrgaAndDepartment($anonymousUser->getOrga(), $user);
    }

    private function getUserForOrga(string $username, array $attributes): UserInterface
    {
        // in this context the given Id is the GatewayName of the organisation
        $orgas = $this->orgaService->getOrgaByFields(['gatewayName' => $username]);
        if (null === $orgas || (is_array($orgas) && empty($orgas))) {
            return $this->createNewUserAndOrgaFromOrga($attributes);
        }

        // orga is already registered in customer, return default user
        // or acting user, if any is given

        $orga = $orgas[0] ?? null;
        if (!$orga instanceof Orga) {
            throw new InvalidArgumentException('Could not find valid orga in SAML request', [$orgas]);
        }

        $orga = $this->updateOrgaWithKnownValues($orga, $attributes);

        $users = $orga->getUsers();

        // return the orga default user
        $user = $users->filter(fn (User $user) => User::DEFAULT_ORGA_USER_NAME === $user->getLastname());

        if (1 === $user->count()) {
            return $user->first();
        }

        throw new InvalidArgumentException('Invalid user attributes given');
    }

    private function getNewUserWithDefaultValues(): User
    {
        $user = new User();
        $user->setAccessConfirmed(true);
        $user->setAlternativeLoginPassword('loginViaSAML');
        $user->setForumNotification(false);
        $user->setInvited(false);
        $user->setNewsletter(false);
        $user->setNewUser(false);
        $user->setNoPiwik(false);
        $user->setPassword('loginViaSAML');
        $user->setProfileCompleted(true);
        $user->setProvidedByIdentityProvider(true);

        return $user;
    }

    /**
     * We could not find any existing orga, so we need to create a new orga
     * with one default user.
     */
    private function createNewUserAndOrgaFromOrga(array $attributes): UserInterface
    {
        $customer = $this->customerService->getCurrentCustomer();

        $orgaName = $attributes['orgaName'][0] ?? '';
        $phone = '';
        $userFirstName = '';
        $userLastName = User::DEFAULT_ORGA_USER_NAME;
        $userEmail = $attributes['email'][0] ?? '';
        $orgaTypeNames = [OrgaType::PUBLIC_AGENCY];

        $orga = $this->orgaService->createOrgaRegister(
            $orgaName,
            $phone,
            $userFirstName,
            $userLastName,
            $userEmail,
            $this->customerService->getCurrentCustomer(),
            $orgaTypeNames
        );

        $orga = $this->updateOrgaWithKnownValues($orga, $attributes);

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
            $this->logger->warning('Could not successfully perform orga registered from SAML event', [$e]);
        }

        // Orga has exactly one master user so far
        $user = $orga->getUsers()->first();
        // set Orga Id as User login to be able to login as the default Orga user on login
        $user->setLogin($attributes['ID'][0] ?? '');
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

    private function updateOrgaWithKnownValues(Orga $orga, array $attributes): Orga
    {
        $orga->setPostalcode($attributes['postalCode'][0] ?? '');
        $orga->setCity($attributes['localityName'][0] ?? '');
        $orga->setStreet($attributes['street'][0] ?? '');
        $orga->setHouseNumber($attributes['houseNumber'][0] ?? '');
        $orga->setGatewayName($attributes['ID'][0] ?? '');

        return $this->orgaService->updateOrga($orga);
    }
}
