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
    private GlobalConfig $globalConfig;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        CustomerService $customerService,
        GlobalConfig $globalConfig
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->customerService = $customerService;
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

                $keycloakResponseValues = new OzgKeycloakResponseValueObject(
                    $client->fetchUserFromToken($accessToken)->toArray()
                );

                // 1 get Desired Roles
                $requestedRoles =  $this->tryAssignRolesToDesiredRoleNames($keycloakResponseValues);
                // 2 handle Organisation / just load it / update it / create it --- handle special case CITIZEN
                $requestedOrga = $this->getOrgaAndHandleRequestedOrgaData($keycloakResponseValues, $requestedRoles);
                // 3 handle user / just load it / update it / create it / and add User to Orga

                $existingUser = $this->tryLoginExistingUser($keycloakResponseValues);

                if ($existingUser) {
                    // TODO: Update user information from keycloak

                    $request->getSession()->set('userId', $existingUser->getId());
                    $existingUser->setGwId($keycloakResponseValues->getProviderId());

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
        OzgKeycloakResponseValueObject $keycloakResponseValueObject,
        array $requestedRoles
    ): Orga {
        if ($this->isUserCitizen($requestedRoles)) {

            // just return the CITIZEN organisation and do not update the orga in this case
            return $this->getCitizenOrga();
        }

        $existingOrga = $this->tryLookupExistingOrga($keycloakResponseValueObject);
        if ($existingOrga) {
            $updatedOrga = $this->updateOrganisation($existingOrga, $requestedRoles, $keycloakResponseValueObject);
            $this->entityManager->persist($updatedOrga);
            $this->entityManager->flush();

            return $existingOrga;
        }

        return $this->createNewOrganisation($requestedRoles, $keycloakResponseValueObject);

    }

    /**
     * @param array<int, Role> $requstedRoles
     */
    private function updateOrganisation(
        Orga $existingOrga,
        array $requstedRoles,
        OzgKeycloakResponseValueObject $keycloakResponseValueObject
    ): Orga {
        $existingOrga->addCustomer($this->customerService->getCurrentCustomer());
        $existingOrga->setGwId($keycloakResponseValueObject->getVerfahrenstraegerGatewayId());
        $existingOrga->setName($keycloakResponseValueObject->getVerfahrenstraeger());
        // what OrgaTypes are needed to be set and accepted regarding the requested Roles?
        $orgaTypesNeededToBeAccepted = $this->getOrgaTypesToSetupRequestedRoles($requstedRoles);
        // are the desired OrgaTypes present and accepted for this organisation/customer
        $currentOrgaStati = $existingOrga->getStatusInCustomers()->filter(
            function (OrgaStatusInCustomer $orgaStatusInCustomer): bool {
                return $orgaStatusInCustomer->getCustomer() === $this->customerService->getCurrentCustomer();
            }
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
        array $requestedRoles,
        OzgKeycloakResponseValueObject $keycloakResponseValueObject
    ): Orga {

        $this->getOrgaTypesToSetupRequestedRoles($keycloakResponseValueObject);

        $department = new Department();
        $department->setName(Department::DEFAULT_DEPARTMENT_NAME);
        $this->em->persist($department);

        $orgaData = [
            'customer'                  => $this->customerService->getCurrentCustomer(),
            'gwId'                      => $keycloakResponseValueObject->getVerfahrenstraegerGatewayId(),
            'name'                      => $keycloakResponseValueObject->getVerfahrenstraeger(),
            'registrationStatuses'      =>
        ];

        // todo implement this
    }


    private function tryCreateNewUser(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?User
    {

        // accumulate new data

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
    private function tryAssignRolesToDesiredRoleNames(OzgKeycloakResponseValueObject $keycloakResponseValueObject): array
    {
        $desiredRoleNames = $keycloakResponseValueObject->getRolleDiPlanBeteiligung();
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

    private function tryLookupExistingOrga(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?Orga
    {
        $existingOrga = $this->tryLookupOrgaByGwId($keycloakResponseValueObject);
        if (null === $existingOrga) {
            $existingOrga = $this->tryLookupOrgaByName($keycloakResponseValueObject);
        }

        return $existingOrga;
    }

    private function getCitizenOrga(): ?Orga
    {
        return $this->entityManager->getRepository(Orga::class)
            ->findOneBy(['id' => User::ANONYMOUS_USER_ORGA_ID]);
    }

    private function tryLookupOrgaByName(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?Orga
    {
        $orga = null;
        if ('' !== $keycloakResponseValueObject->getVerfahrenstraeger()) {
            /** @var Orga $orga **/
            $orga = $this->entityManager->getRepository(Orga::class)
                ->findOneBy(['gwId' => $keycloakResponseValueObject->getVerfahrenstraeger()]);
//            if ($orga->getCustomers()->contains($this->customerService->getCurrentCustomer())) {
//                return $orga;
//            }
        }

        return $orga;
    }

    private function tryLookupOrgaByGwId(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?Orga
    {
        $orga = null;
        if ('' !== $keycloakResponseValueObject->getVerfahrenstraegerGatewayId()) {
            $orga = $this->entityManager->getRepository(Orga::class)
                ->findOneBy(['gwId' => $keycloakResponseValueObject->getVerfahrenstraegerGatewayId()]);
        }

        return $orga;
    }

    private function updateExistingDplanUser(User $dplanUser, OzgKeycloakResponseValueObject $keycloakUser): User
    {
        // How to update user roles?
        if (null !== $keycloakUser->getRolleDiPlanBeteiligung()) {
            //$dplanUser->setRoleInCustomers();
            //$dplanUser->setRolesAllowed();
        }

        if (null !== $keycloakUser->getProviderId()) {
            $dplanUser->setGwId($keycloakUser->getProviderId());
        }

        if (null !== $keycloakUser->getNutzerId()) {
            $dplanUser->setLogin($keycloakUser->getNutzerId());
        }

        // Are getEmailVerified() correct here?
        if (null !== $keycloakUser->getEmailAdresse() && $keycloakUser->getEmailVerified()) {
            $dplanUser->setEmail($keycloakUser->getEmailAdresse());
        }

        if (null !== $keycloakUser->getVollerName()) {
            $fullNameArray = explode(' ', $keycloakUser->getVollerName());
            if (false !== $fullNameArray) {
                $dplanUser->setFirstname($fullNameArray[0]);
                1 < count($fullNameArray) ? $lastname = $fullNameArray[1] : $lastname = '';
                $dplanUser->setLastname($lastname);
                // Maybe set only firstname or lastname
                // Add fullname to User entity
            }
        }

        if (null !== $keycloakUser->getVerfahrenstraeger()) {
            // Exist orgas which share the same name?
            $existingOrga = $this->entityManager->getRepository(Orga::class)
                ->findOneBy(['name' => $keycloakUser->getVerfahrenstraeger()]);
            if (null === $existingOrga) {
                // TODO: Create new Orga
            }
            $dplanUser->setOrga($existingOrga);
        }

        return $dplanUser;
    }

    private function CheckIfUserAttributeHasToUpdate($dplanUserAttribute, $keycloakUserAttribute): bool
    {
        return null !== $keycloakUserAttribute && $dplanUserAttribute === $keycloakUserAttribute;
    }

    private function tryLoginExistingUser(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?User
    {
        // 1) have they logged in with Keycloak before? Easy!
        $existingUser = $this->tryLoginViaGatewayId($keycloakResponseValueObject);
        if (null === $existingUser) {
            // 2) do we have a matching user by login
            $existingUser = $this->tryLoginViaLoginAttribute($keycloakResponseValueObject);
        }
        if (null === $existingUser) {
            // 3) do we have a matching user by email?
            $existingUser = $this->tryLoginViaEmail($keycloakResponseValueObject);
        }

        return $existingUser;
    }

    private function tryLoginViaGatewayId(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['gwId' => $keycloakResponseValueObject->getProviderId()]);
    }

    private function tryLoginViaLoginAttribute(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['login' => $keycloakResponseValueObject->getNutzerId()]);
    }

    private function tryLoginViaEmail(OzgKeycloakResponseValueObject $keycloakResponseValueObject): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $keycloakResponseValueObject->getEmailAdresse()]);
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
