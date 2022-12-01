<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakResponseValueObject;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use demosplan\DemosPlanUserBundle\Repository\UserRoleInCustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class OzgKeycloakAuthenticator extends OAuth2Authenticator implements AuthenticationEntrypointInterface
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;
    private CustomerService $customerService;
    private UserRoleInCustomerRepository $userRoleInCustomerRepository;
    private GlobalConfig $globalConfig;
    private OzgKeycloakResponseValueObject $ozgKeycloakResponseValueObject;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        CustomerService $customerService,
        UserRoleInCustomerRepository $userRoleInCustomerRepository,
        GlobalConfig $globalConfig
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->customerService = $customerService;
        $this->userRoleInCustomerRepository = $userRoleInCustomerRepository;
        $this->globalConfig = $globalConfig;
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return $request->attributes->get('_route') === 'connect_keycloak_ozg_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('keycloak_ozg');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $request) {

                $this->ozgKeycloakResponseValueObject = new OzgKeycloakResponseValueObject(
                    $client->fetchUserFromToken($accessToken)->toArray()
                );

                // 1 get Desired Roles
                $requestedRoles =  $this->tryAssignRolesToDesiredRoleNames();
                // 2 handle Organisation / just load it / update it / create it --- handle special case CITIZEN
                $requestedOrga = $this->getOrgaAndHandleRequestedOrgaData($requestedRoles);
                // 3 handle user / just load it / update it / create it / and add User to Orga

                $existingUser = $this->tryLoginExistingUser();

                if ($existingUser) {
                    // Update user information from keycloak
                    $request->getSession()->set('userId', $existingUser->getId());
                    $existingUser = $this->updateExistingDplanUser($existingUser);

                    $this->entityManager->persist($existingUser);
                    $this->entityManager->flush();

                    return $existingUser;
                }

                // 4) Create new User using keycloak data
                // TODO: Save user information from keycloak



                $newUser = null;

                // 5) Handle total garbage data

                return $newUser;
            })
        );
    }

    private function getOrgaAndHandleRequestedOrgaData(
        array $requestedRoles
    ): Orga {
        if ($this->isUserCitizen($requestedRoles)) {

            // just return the CITIZEN organisation and do not update the orga in this case
            return $this->getCitizenOrga();
        }

        $existingOrga = $this->tryLookupExistingOrga();
        if ($existingOrga) {
            $updatedOrga = $this->updateOrganisation($existingOrga, $requestedRoles);
            $this->entityManager->persist($updatedOrga);
            $this->entityManager->flush();

            return $existingOrga;
        }

        return $this->createNewOrganisation($requestedRoles);

    }

    /**
     * @param array<int, Role> $requstedRoles
     */
    private function updateOrganisation(
        Orga $existingOrga,
        array $requstedRoles
    ): Orga {
        $existingOrga->addCustomer($this->customerService->getCurrentCustomer());
        $existingOrga->setGwId($this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId());
        $existingOrga->setName($this->ozgKeycloakResponseValueObject->getVerfahrenstraeger());
        // what OrgaTypes are needed to be set and accepted regarding the requested Roles?
        $orgaTypesNeededToBeAccepted = $this->getOrgaTypesToSetupRequestedRoles($requstedRoles);
        // are the desired OrgaTypes present and accepted for this organisation/customer
        $currentOrgaStati = $existingOrga->getStatusInCustomers()->filter(
            fn (OrgaStatusInCustomer $orgaStatusInCustomer): bool =>
                $orgaStatusInCustomer->getCustomer() === $this->customerService->getCurrentCustomer()
        );
        foreach ($orgaTypesNeededToBeAccepted as $neededOrgaType) {
            $typeExists = false;
            /** @var OrgaStatusInCustomer $orgaStatusInCurrentCustomer */
            foreach ($currentOrgaStati as $orgaStatusInCurrentCustomer) {
                if ($orgaStatusInCurrentCustomer->getOrgaType()->getName() === $neededOrgaType) {
                    $orgaStatusInCurrentCustomer->setStatus(OrgaStatusInCustomer::STATUS_ACCEPTED);
                    $this->entityManager->persist($orgaStatusInCurrentCustomer);
                    $typeExists = true;
                }
            }
            if (!$typeExists) {
                $existingOrga->addCustomerAndOrgaType($this->customerService->getCurrentCustomer(), $neededOrgaType);
            }
        }
        $this->entityManager->persist($existingOrga);
        $this->entityManager->flush();

        return $existingOrga;
    }

    /**
     * @param array<int, Role> $requestedRoles
     */
    private function createNewOrganisation(
        array $requestedRoles
    ): Orga {

        $this->getOrgaTypesToSetupRequestedRoles($requestedRoles);

        $department = new Department();
        $department->setName(Department::DEFAULT_DEPARTMENT_NAME);
        $this->em->persist($department);

        $orgaData = [
            'customer'                  => $this->customerService->getCurrentCustomer(),
            'gwId'                      => $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId(),
            'name'                      => $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger(),
            'registrationStatuses'      => null,
        ];

        // todo implement this
    }


    private function tryCreateNewUser(): ?User
    {

        //TODO: accumulate new data

    }

    private function isUserCitizen(array $desiredRoles): bool
    {
        /** @var Role $role */
        foreach ($desiredRoles as $role) {
            if ($role->getCode() === Role::CITIZEN) {

                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, Role> $desiredRoles
     * @return array<int, string>
     */
    private function getOrgaTypesToSetupRequestedRoles(array $requestedRoles): array
    {
        $orgaTypesNeeded = [];
        /** @var Role $requestedRole */
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
     */
    private function tryAssignRolesToDesiredRoleNames(): array
    {
        $desiredRoleNames = $this->ozgKeycloakResponseValueObject->getRolleDiPlanBeteiligung();
        $allRoles = $this->entityManager->getRepository(Role::class)->findAll();

        // try to map the desired roleNames to a Role entity
        $desiredRoles = [];
        /** @var Role $role */
        foreach ($allRoles as $role) {
            if (in_array($role->getName(), $desiredRoleNames, true)) {
                $desiredRoles[] = $role;
            }
        }

        $unavailableRoleNames = $this->findNonExistantRoles($desiredRoleNames, $desiredRoles);
        // Todo -> User requested a RoleName that is unknown to us
        $unavailableRolesInProject = $this->findNotAvailableRolesInProject($desiredRoles);
        // Todo -> User requested a Role that is not available within this project

        return $desiredRoles;
    }


    private function findNotAvailableRolesInProject(array $desiredRoles): array
    {
        $unavailableRoles = [];
        /** @var Role $role */
        foreach ($desiredRoles as $role) {
            $roleCode = $role->getCode();
            if (!in_array($roleCode, $this->globalConfig->getRolesAllowed(), true)) {
                $unavailableRoles[] = $role;
            }
        }

        return $unavailableRoles;
    }

    /**
     * @param array<int, string> $desiredRoleNames
     * @param array<int, Role> $desiredRoles
     * @return array<int, string>
     */
    private function findNonExistantRoles(array $desiredRoleNames, array $desiredRoles): array
    {
        $unavailableRoles = [];
        if (count($desiredRoles) !== count($desiredRoleNames)) {
            $unavailableRoles = array_filter(
                $desiredRoleNames,
                static function (string $desiredRoleName) use ($desiredRoles): bool {
                    /** @var Role $role */
                    foreach ($desiredRoles as $role) {
                        if ($role->getName() === $desiredRoleName) {

                            return false;
                        }
                    }

                    return true;
                }
            );
        }

        return $unavailableRoles;
    }

    private function tryLookupExistingOrga(): ?Orga
    {
        $existingOrga = $this->tryLookupOrgaByGwId();
        if (null === $existingOrga) {
            $existingOrga = $this->tryLookupOrgaByName();
        }

        return $existingOrga;
    }

    private function getCitizenOrga(): ?Orga
    {
        return $this->entityManager->getRepository(Orga::class)
            ->findOneBy(['id' => User::ANONYMOUS_USER_ORGA_ID]);
    }

    private function tryLookupOrgaByName(): ?Orga
    {
        $orga = null;
        if ('' !== $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger()) {
            /** @var Orga $orga **/
            $orga = $this->entityManager->getRepository(Orga::class)
                ->findOneBy(['gwId' => $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger()]);
//            if ($orga->getCustomers()->contains($this->customerService->getCurrentCustomer())) {
//                return $orga;
//            }
        }

        return $orga;
    }

    private function tryLookupOrgaByGwId(): ?Orga
    {
        $orga = null;
        if ('' !== $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId()) {
            $orga = $this->entityManager->getRepository(Orga::class)
                ->findOneBy(['gwId' => $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId()]);
        }

        return $orga;
    }

    private function updateExistingDplanUser(User $dplanUser): User
    {
        $requestedRoles = $this->tryAssignRolesToDesiredRoleNames();
        $existingOrga = $this->entityManager->getRepository(Orga::class)
            ->findOneBy(['name' => $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger()]);
        // To update the user roles we clear them first and set the roles from keycloak.
        if ($this->hasUserAttributeToUpdate($dplanUser->getGwId(), $this->ozgKeycloakResponseValueObject->getProviderId())) {
            $dplanUser->setGwId($this->ozgKeycloakResponseValueObject->getProviderId());
        }

        if ($this->hasUserAttributeToUpdate($dplanUser->getRoles(), $this->ozgKeycloakResponseValueObject->getRolleDiPlanBeteiligung())) {
            $customer = $this->customerService->getCurrentCustomer();
            $customerId = $customer->getId();
            $userId = $dplanUser->getId();
            $this->userRoleInCustomerRepository->clearUserRoles($userId, $customerId);
            $dplanUser->setDplanroles($requestedRoles, $customer);
        }

        if ($this->hasUserAttributeToUpdate($dplanUser->getLogin(), $this->ozgKeycloakResponseValueObject->getNutzerId())) {
            $dplanUser->setLogin($this->ozgKeycloakResponseValueObject->getNutzerId());
        }

        if ($this->hasUserAttributeToUpdate($dplanUser->getEmail(), $this->ozgKeycloakResponseValueObject->getEmailAdresse())) {
            $dplanUser->setEmail($this->ozgKeycloakResponseValueObject->getEmailAdresse());
        }

        if ($this->hasUserAttributeToUpdate($dplanUser->getFullname(), $this->ozgKeycloakResponseValueObject->getVollerName())) {
            $dplanUser->setFirstname('');
            $dplanUser->setLastname($this->ozgKeycloakResponseValueObject->getVollerName());
        }

        if ($this->hasUserAttributeToUpdate($dplanUser->getOrgaName(), $this->ozgKeycloakResponseValueObject->getVerfahrenstraeger())) {
            // Exist orgas which share the same name? Technically possible, but in reality not.
            if (null === $existingOrga) {
                $this->createNewOrganisation($requestedRoles);
            }
            $dplanUser->setOrga($existingOrga);
        }

        /**
         * @var Orga $existingOrga
         */
        if (null !== $existingOrga &&
            $this->hasUserAttributeToUpdate($existingOrga->getGwId(), $this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId())) {
            $existingOrga->setGwId($this->ozgKeycloakResponseValueObject->getVerfahrenstraegerGatewayId());
        }

        return $dplanUser;
    }

    private function hasUserAttributeToUpdate($dplanUserAttribute, $keycloakUserAttribute): bool
    {
        return $dplanUserAttribute !== $keycloakUserAttribute;
    }

    private function tryLoginExistingUser(): ?User
    {
        // 1) have they logged in with Keycloak before? Easy!
        $existingUser = $this->tryLoginViaGatewayId();
        if (null === $existingUser) {
            // 2) do we have a matching user by login
            $existingUser = $this->tryLoginViaLoginAttribute();
        }
        if (null === $existingUser) {
            // 3) do we have a matching user by email?
            $existingUser = $this->tryLoginViaEmail();
        }

        return $existingUser;
    }

    private function tryLoginViaGatewayId(): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['gwId' => $this->ozgKeycloakResponseValueObject->getProviderId()]);
    }

    private function tryLoginViaLoginAttribute(): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['login' => $this->ozgKeycloakResponseValueObject->getNutzerId()]);
    }

    private function tryLoginViaEmail(): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $this->ozgKeycloakResponseValueObject->getEmailAdresse()]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // change "app_homepage" to some route in your app
        $targetUrl = $this->router->generate('core_home_loggedin');

        return new RedirectResponse($targetUrl);

        // or, on success, let the request continue to be handled by the controller
        //return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     * This redirects to the 'login'.
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            '/connect/keycloak_ozg', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
