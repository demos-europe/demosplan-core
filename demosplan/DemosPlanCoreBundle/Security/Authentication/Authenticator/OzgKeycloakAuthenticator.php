<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakResponseValueObject;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
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

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
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
