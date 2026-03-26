<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\OAuth;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\ExpirationTimestampRequestListener;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\KeycloakTokenRefreshService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\PendingRequestCacheService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenExpirationService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\AbstractOzgKeycloakAuthenticator;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\UserFromSecurityUserProvider;
use League\OAuth2\Client\Token\AccessToken;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface as SecurityTokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class LoginOnlyModeTest extends TestCase
{
    /** @var ExpirationTimestampRequestListener */
    protected $sut;

    private MockObject&OzgKeycloakSessionManager $sessionManager;
    private MockObject&TokenExpirationService $tokenExpirationService;
    private MockObject&KeycloakTokenRefreshService $tokenRefreshService;
    private MockObject&OAuthTokenRepository $oauthTokenRepository;
    private MockObject&TokenStorageInterface $tokenStorage;
    private MockObject&UserRepository $userRepository;
    private MockObject&Security $security;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionManager = $this->createMock(OzgKeycloakSessionManager::class);
        $this->tokenExpirationService = $this->createMock(TokenExpirationService::class);
        $this->tokenRefreshService = $this->createMock(KeycloakTokenRefreshService::class);
        $this->oauthTokenRepository = $this->createMock(OAuthTokenRepository::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->security = $this->createMock(Security::class);

        $this->sut = new ExpirationTimestampRequestListener(
            $this->tokenRefreshService,
            new NullLogger(),
            $this->oauthTokenRepository,
            $this->sessionManager,
            $this->security,
            $this->tokenExpirationService,
            new UserFromSecurityUserProvider($this->tokenStorage, $this->userRepository),
        );
    }

    // ===== Login-only mode skips token management =====

    public function testListenerSkipsTokenManagementInLoginOnlyMode(): void
    {
        // Arrange: IdP user in login-only mode
        $user = $this->createIdpUser();
        $this->arrangeSecurityUser($user);
        $this->sessionManager->method('isKeycloakLoginOnly')->willReturn(true);
        $this->sessionManager->method('shouldSkipInProductionWithoutKeycloak')->willReturn(false);

        // Assert: handleExpiredTokens is NEVER called
        $this->tokenExpirationService->expects(self::never())->method('handleExpiredTokens');

        // Act
        $this->sut->onKernelController($this->createControllerEvent());
    }

    public function testListenerSkipsTokenManagementForNonIdpUser(): void
    {
        // Arrange: non-IdP user (form-login)
        $user = $this->createNonIdpUser();
        $this->arrangeSecurityUser($user);
        $this->sessionManager->method('shouldSkipInProductionWithoutKeycloak')->willReturn(false);

        // Assert: handleExpiredTokens is NEVER called
        $this->tokenExpirationService->expects(self::never())->method('handleExpiredTokens');

        // Act
        $this->sut->onKernelController($this->createControllerEvent());
    }

    public function testListenerSkipsTokenManagementForNullUser(): void
    {
        // Arrange: security user exists but no matching User entity in DB
        $this->arrangeSecurityUserWithoutEntity();
        $this->sessionManager->method('shouldSkipInProductionWithoutKeycloak')->willReturn(false);

        // Assert: handleExpiredTokens is NEVER called
        $this->tokenExpirationService->expects(self::never())->method('handleExpiredTokens');

        // Act
        $this->sut->onKernelController($this->createControllerEvent());
    }

    public function testListenerSkipsForFunctionalUser(): void
    {
        // Arrange: non-human user (AnonymousUser, AiApiUser, etc.)
        $functionalUser = $this->createMock(FunctionalUser::class);
        $this->security->method('getUser')->willReturn($functionalUser);
        $this->sessionManager->method('shouldSkipInProductionWithoutKeycloak')->willReturn(false);

        // Assert: handleExpiredTokens is NEVER called
        $this->tokenExpirationService->expects(self::never())->method('handleExpiredTokens');

        // Act
        $this->sut->onKernelController($this->createControllerEvent());
    }

    // ===== Normal IdP mode checks tokens =====

    public function testListenerSkipsWhenTokensAreValid(): void
    {
        // Arrange: IdP user, NOT login-only, tokens valid
        $user = $this->createIdpUser();
        $this->arrangeSecurityUser($user);
        $this->sessionManager->method('isKeycloakLoginOnly')->willReturn(false);
        $this->sessionManager->method('shouldSkipInProductionWithoutKeycloak')->willReturn(false);

        $oauthToken = new OAuthToken();
        $oauthToken->setUser($user);
        $this->oauthTokenRepository->method('findByUserId')->willReturn($oauthToken);
        $this->tokenRefreshService->method('hasValidTokens')->willReturn(true);

        // Assert: handleExpiredTokens is NOT called (tokens are valid)
        $this->tokenExpirationService->expects(self::never())->method('handleExpiredTokens');

        // Act
        $this->sut->onKernelController($this->createControllerEvent());
    }

    public function testListenerHandlesExpiredTokensWhenNoTokenExists(): void
    {
        // Arrange: IdP user, NOT login-only, no token in DB
        $user = $this->createIdpUser();
        $this->arrangeSecurityUser($user);
        $this->sessionManager->method('isKeycloakLoginOnly')->willReturn(false);
        $this->sessionManager->method('shouldSkipInProductionWithoutKeycloak')->willReturn(false);

        // Assert: handleExpiredTokens IS called (no token found → treated as expired)
        $this->tokenExpirationService->expects(self::once())->method('handleExpiredTokens');

        // Act
        $this->sut->onKernelController($this->createControllerEvent());
    }

    // ===== Authenticator: login-only mode stores id_token and session expiry =====

    public function testLoginOnlyModeStoresIdTokenForLogout(): void
    {
        // Arrange: login-only mode, AccessToken with id_token
        $this->sessionManager->method('isKeycloakLoginOnly')->willReturn(true);

        // Assert: storeIdTokenForLogout is called with the raw id_token
        $this->sessionManager->expects(self::once())
            ->method('storeIdTokenForLogout')
            ->with(self::isInstanceOf(Session::class), 'raw-id-token-value');

        // Act
        $authenticator = $this->createAuthenticator();
        $accessToken = new AccessToken([
            'access_token' => 'test-access-token',
            'expires'      => time() + 300,
            'id_token'     => 'raw-id-token-value',
        ]);

        $authenticator->callHandleAuthenticationSuccess(
            $this->createAuthRequest(),
            $this->createSecurityToken(),
            $accessToken,
        );
    }

    public function testLoginOnlyModeInjectsSessionExpiration(): void
    {
        // Arrange: login-only mode
        $this->sessionManager->method('isKeycloakLoginOnly')->willReturn(true);

        // Assert: injectTokenExpirationIntoSession is called
        $this->sessionManager->expects(self::once())
            ->method('injectTokenExpirationIntoSession');

        // Act
        $authenticator = $this->createAuthenticator();
        $accessToken = new AccessToken([
            'access_token' => 'test-access-token',
            'expires'      => time() + 300,
            'id_token'     => 'raw-id-token-value',
        ]);

        $authenticator->callHandleAuthenticationSuccess(
            $this->createAuthRequest(),
            $this->createSecurityToken(),
            $accessToken,
        );
    }

    public function testLoginOnlyModeDoesNotStoreTokensInDatabase(): void
    {
        // Arrange: login-only mode
        $this->sessionManager->method('isKeycloakLoginOnly')->willReturn(true);
        $oauthTokenStorageService = $this->createMock(OAuthTokenStorageService::class);

        // Assert: storeTokens is NEVER called
        $oauthTokenStorageService->expects(self::never())->method('storeTokens');

        // Act
        $authenticator = $this->createAuthenticator($oauthTokenStorageService);
        $accessToken = new AccessToken([
            'access_token' => 'test-access-token',
            'expires'      => time() + 300,
            'id_token'     => 'raw-id-token-value',
        ]);

        $authenticator->callHandleAuthenticationSuccess(
            $this->createAuthRequest(),
            $this->createSecurityToken(),
            $accessToken,
        );
    }

    // ===== Helper methods =====

    private function arrangeSecurityUser(User $user): void
    {
        $this->security->method('getUser')->willReturn($user);

        $securityToken = $this->createMock(SecurityTokenInterface::class);
        $securityToken->method('getUser')->willReturn($user);
        $this->tokenStorage->method('getToken')->willReturn($securityToken);

        $this->userRepository->method('findOneBy')->willReturn($user);
    }

    private function arrangeSecurityUserWithoutEntity(): void
    {
        $securityUser = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($securityUser);

        $securityToken = $this->createMock(SecurityTokenInterface::class);
        $securityToken->method('getUser')->willReturn($securityUser);
        $this->tokenStorage->method('getToken')->willReturn($securityToken);

        $this->userRepository->method('findOneBy')->willReturn(null);
    }

    private function createControllerEvent(): ControllerEvent
    {
        $request = Request::create('/verfahren/123/import');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->start();

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function () {},
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function createIdpUser(): MockObject&User
    {
        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);
        $user->method('getId')->willReturn('test-user-id');

        return $user;
    }

    private function createNonIdpUser(): MockObject&User
    {
        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(false);

        return $user;
    }

    private function createAuthenticator(?OAuthTokenStorageService $oauthTokenStorageService = null): object
    {
        $oauthTokenStorageService ??= $this->createMock(OAuthTokenStorageService::class);
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name): string => '/'.$name
        );

        return new class(new NullLogger(), $router, $this->createMock(CurrentOrganisationService::class), $this->createMock(MessageBagInterface::class), $oauthTokenStorageService, $this->createMock(PendingRequestCacheService::class), $this->sessionManager) extends AbstractOzgKeycloakAuthenticator {
            public function supports(Request $request): ?bool
            {
                return false;
            }

            public function authenticate(Request $request): Passport
            {
                throw new LogicException('Not used in test');
            }

            public function onAuthenticationSuccess(Request $request, SecurityTokenInterface $token, string $firewallName): ?Response
            {
                return null;
            }

            public function callHandleAuthenticationSuccess(Request $request, SecurityTokenInterface $token, ?AccessToken $accessToken): Response
            {
                return $this->handleAuthenticationSuccess($request, $token, $accessToken);
            }
        };
    }

    private function createAuthRequest(): Request
    {
        $request = Request::create('/test');
        $session = new Session(new MockArraySessionStorage());
        $session->set('userId', 'test-user-id');
        $request->setSession($session);

        return $request;
    }

    private function createSecurityToken(): MockObject&SecurityTokenInterface
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('test-user-id');

        $token = $this->createMock(SecurityTokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
