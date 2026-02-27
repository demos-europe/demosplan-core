<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\CustomerServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\CustomerOAuthConfig;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventSubscriber\LogoutSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakLogoutManager;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriberTest extends TestCase
{
    private LogoutSubscriber $sut;
    private MockObject $logger;
    private MockObject $parameterBag;
    private MockObject $permissions;
    private MockObject $urlGenerator;
    private MockObject $ozgKeycloakLogoutManager;
    private MockObject $customerService;
    private MockObject $configRepository;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->ozgKeycloakLogoutManager = $this->createMock(OzgKeycloakLogoutManager::class);
        $this->customerService = $this->createMock(CustomerServiceInterface::class);
        $this->configRepository = $this->createMock(CustomerOAuthConfigRepository::class);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerService->method('getCurrentCustomer')->willReturn($customer);
        // Default: no per-customer config — tests that need one override this
        $this->configRepository->method('findByCustomer')->willReturn(null);

        // Create a partial mock to override redirect methods
        $this->sut = $this->getMockBuilder(LogoutSubscriber::class)
            ->setConstructorArgs([
                $this->logger,
                $this->parameterBag,
                $this->permissions,
                $this->urlGenerator,
                $this->ozgKeycloakLogoutManager,
                $this->customerService,
                $this->configRepository,
            ])
            ->onlyMethods(['redirect', 'redirectToRoute'])
            ->getMock();
    }

    public function testAzureLogoutTriggeredWhenUserProvidedByIdentityProvider(): void
    {
        // Arrange
        $azureLogoutRoute = 'https://login.microsoftonline.com/tenant/oauth2/v2.0/logout?post_logout_redirect_uri=https://example.com/connect/azure/logout';

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $event = $this->createMock(LogoutEvent::class);
        $event->method('getToken')->willReturn($token);
        $event->method('getResponse')->willReturn(null);

        $this->parameterBag->method('get')
            ->willReturnCallback(function ($key, $default = '') {
                return match ($key) {
                    'oauth_keycloak_logout_route' => '', // No Keycloak logout
                    'oauth_azure_logout_route'    => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/logout?post_logout_redirect_uri=https://example.com/connect/azure/logout',
                    default                       => $default,
                };
            });

        // Mock OzgKeycloakLogoutManager to not be configured for Azure test
        $this->ozgKeycloakLogoutManager->method('isKeycloakConfigured')->willReturn(false);

        $mockRedirectResponse = $this->createMockRedirectResponse();

        $this->sut->expects($this->once())
            ->method('redirect')
            ->with($azureLogoutRoute)
            ->willReturn($mockRedirectResponse);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($mockRedirectResponse);

        // Act
        $this->sut->onLogout($event);
    }

    public function testIdentityProviderLogoutNotTriggeredWhenUserNotProvidedByIdentityProvider(): void
    {
        // Arrange
        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(false); // Regular user

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $event = $this->createMock(LogoutEvent::class);
        $event->method('getToken')->willReturn($token);
        $event->method('getResponse')->willReturn(null);

        $this->parameterBag->method('get')
            ->willReturnCallback(function ($key, $default = '') {
                return match ($key) {
                    'oauth_keycloak_logout_route' => '',
                    'oauth_azure_logout_route'    => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/logout?post_logout_redirect_uri=https://example.com/connect/azure/logout',
                    default                       => $default,
                };
            });

        // Mock OzgKeycloakLogoutManager to not be configured
        $this->ozgKeycloakLogoutManager->method('isKeycloakConfigured')->willReturn(false);

        // Identity provider logout should not be triggered for regular users
        $this->sut->expects($this->never())
            ->method('redirect');

        $mockRedirectResponse = $this->createMockRedirectResponse();

        $this->sut->expects($this->once())
            ->method('redirectToRoute')
            ->with('core_home')
            ->willReturn($mockRedirectResponse);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($mockRedirectResponse);

        // Act
        $this->sut->onLogout($event);
    }

    public function testIdentityProviderLogoutNotTriggeredWhenUserIsNull(): void
    {
        // Arrange
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null); // No user

        $event = $this->createMock(LogoutEvent::class);
        $event->method('getToken')->willReturn($token);
        $event->method('getResponse')->willReturn(null);

        $this->parameterBag->method('get')
            ->willReturnCallback(function ($key, $default = '') {
                return match ($key) {
                    'oauth_keycloak_logout_route' => 'https://keycloak.example.com/logout',
                    'oauth_azure_logout_route'    => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/logout',
                    default                       => $default,
                };
            });

        // Mock OzgKeycloakLogoutManager to not be configured
        $this->ozgKeycloakLogoutManager->method('isKeycloakConfigured')->willReturn(false);

        // Identity provider logout should not be triggered when no user
        $this->sut->expects($this->never())
            ->method('redirect');

        $mockRedirectResponse = $this->createMockRedirectResponse();

        $this->sut->expects($this->once())
            ->method('redirectToRoute')
            ->with('core_home')
            ->willReturn($mockRedirectResponse);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($mockRedirectResponse);

        // Act
        $this->sut->onLogout($event);
    }

    public function testKeycloakLogoutTriggeredWhenUserProvidedByIdentityProvider(): void
    {
        // Arrange
        $originalKeycloakRoute = 'https://keycloak.example.com/auth/realms/demo/protocol/openid-connect/logout?post_logout_redirect_uri=https://example.com/home';
        $expectedModifiedRoute = 'https://keycloak.example.com/auth/realms/demo/protocol/openid-connect/logout?post_logout_redirect_uri=https://test.example.com/home';
        $keycloakToken = 'mock_keycloak_token';

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Mock session and request
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn($keycloakToken);
        $session->expects($this->once())->method('invalidate');

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        $event = $this->createMock(LogoutEvent::class);
        $event->method('getToken')->willReturn($token);
        $event->method('getResponse')->willReturn(null);
        $event->method('getRequest')->willReturn($request);

        $this->parameterBag->method('get')
            ->willReturnCallback(function ($key, $default = '') use ($originalKeycloakRoute) {
                return match ($key) {
                    'oauth_keycloak_logout_route' => $originalKeycloakRoute,
                    'oauth_azure_logout_route'    => '', // No Azure logout
                    default                       => $default,
                };
            });

        // Mock OzgKeycloakLogoutManager
        $this->ozgKeycloakLogoutManager->method('isKeycloakConfigured')->willReturn(true);
        $this->ozgKeycloakLogoutManager->method('getLogoutUrl')
            ->with($originalKeycloakRoute, $keycloakToken)
            ->willReturn($expectedModifiedRoute);

        $mockRedirectResponse = $this->createMockRedirectResponse();

        $this->sut->expects($this->once())
            ->method('redirect')
            ->with($expectedModifiedRoute) // Expect the modified route with subdomain
            ->willReturn($mockRedirectResponse);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($mockRedirectResponse);

        // Act
        $this->sut->onLogout($event);
    }

    public function testLogoutLandingPageUsedWhenPermissionEnabled(): void
    {
        // Arrange
        $event = $this->createMock(LogoutEvent::class);
        $event->method('getToken')->willReturn(null);
        $event->method('getResponse')->willReturn(null);

        $this->parameterBag->method('get')
            ->willReturnCallback(function ($key, $default = '') {
                return match ($key) {
                    'oauth_keycloak_logout_route' => '',
                    'oauth_azure_logout_route'    => '',
                    default                       => $default,
                };
            });

        // Mock OzgKeycloakLogoutManager to not be configured
        $this->ozgKeycloakLogoutManager->method('isKeycloakConfigured')->willReturn(false);

        $this->permissions->method('hasPermission')
            ->with('feature_has_logout_landing_page')
            ->willReturn(true);

        $mockRedirectResponse1 = $this->createMockRedirectResponse();
        $mockRedirectResponse2 = $this->createMockRedirectResponse();

        $this->sut->expects($this->exactly(2))
            ->method('redirectToRoute')
            ->willReturnCallback(function ($route) use ($mockRedirectResponse1, $mockRedirectResponse2) {
                return match ($route) {
                    'core_home'                     => $mockRedirectResponse1,
                    'DemosPlan_user_logout_success' => $mockRedirectResponse2,
                    default                         => $mockRedirectResponse1,
                };
            });

        $event->expects($this->once())
            ->method('setResponse')
            ->with($mockRedirectResponse2); // Should set the landing page response

        // Act
        $this->sut->onLogout($event);
    }

    public function testPerCustomerKeycloakLogoutRouteOverridesGlobalParameter(): void
    {
        // Arrange
        $globalRoute = 'https://keycloak.example.com/logout?post_logout_redirect_uri=https://example.com';
        $perCustomerRoute = 'https://keycloak.hh.example.com/logout?post_logout_redirect_uri=https://hh.example.com';
        $adjustedRoute = 'https://keycloak.hh.example.com/logout?post_logout_redirect_uri=https://hh.hh.example.com';
        $keycloakToken = 'mock_keycloak_token';

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn($keycloakToken);
        $session->expects($this->once())->method('invalidate');

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        $event = $this->createMock(LogoutEvent::class);
        $event->method('getToken')->willReturn($token);
        $event->method('getResponse')->willReturn(null);
        $event->method('getRequest')->willReturn($request);

        $this->parameterBag->method('get')
            ->willReturnCallback(fn ($key, $default = '') => match ($key) {
                'oauth_keycloak_logout_route' => $globalRoute,
                'oauth_azure_logout_route'    => '',
                default                       => $default,
            });

        // Per-customer config provides its own logout route
        $customerConfig = $this->createMock(CustomerOAuthConfig::class);
        $customerConfig->method('getKeycloakLogoutRoute')->willReturn($perCustomerRoute);
        $this->configRepository = $this->createMock(CustomerOAuthConfigRepository::class);
        $this->configRepository->method('findByCustomer')->willReturn($customerConfig);

        $this->ozgKeycloakLogoutManager->method('isKeycloakConfigured')->willReturn(true);
        $this->ozgKeycloakLogoutManager->method('getLogoutUrl')
            ->with($perCustomerRoute, $keycloakToken)
            ->willReturn($adjustedRoute);

        // Rebuild sut with overridden configRepository
        $this->sut = $this->getMockBuilder(LogoutSubscriber::class)
            ->setConstructorArgs([
                $this->logger,
                $this->parameterBag,
                $this->permissions,
                $this->urlGenerator,
                $this->ozgKeycloakLogoutManager,
                $this->customerService,
                $this->configRepository,
            ])
            ->onlyMethods(['redirect', 'redirectToRoute'])
            ->getMock();

        $mockRedirectResponse = $this->createMockRedirectResponse();

        $this->sut->expects($this->once())
            ->method('redirect')
            ->with($adjustedRoute)
            ->willReturn($mockRedirectResponse);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($mockRedirectResponse);

        // Act
        $this->sut->onLogout($event);
    }

    private function createMockRedirectResponse(): MockObject
    {
        $mockRedirectResponse = $this->createMock(RedirectResponse::class);
        $mockRedirectResponse->headers = $this->createMock(ResponseHeaderBag::class);
        $mockRedirectResponse->headers->method('clearCookie')->willReturn(null);

        return $mockRedirectResponse;
    }
}
