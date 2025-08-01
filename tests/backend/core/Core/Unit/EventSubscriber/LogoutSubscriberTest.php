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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventSubscriber\LogoutSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriberTest extends TestCase
{
    private LogoutSubscriber $sut;
    private MockObject $customerService;
    private MockObject $logger;
    private MockObject $parameterBag;
    private MockObject $permissions;
    private MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->customerService = $this->createMock(CustomerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        // Create a partial mock to override redirect methods
        $this->sut = $this->getMockBuilder(LogoutSubscriber::class)
            ->setConstructorArgs([
                $this->customerService,
                $this->logger,
                $this->parameterBag,
                $this->permissions,
                $this->urlGenerator,
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

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $event = $this->createMock(LogoutEvent::class);
        $event->method('getToken')->willReturn($token);
        $event->method('getResponse')->willReturn(null);

        $this->parameterBag->method('get')
            ->willReturnCallback(function ($key, $default = '') use ($originalKeycloakRoute) {
                return match ($key) {
                    'oauth_keycloak_logout_route' => $originalKeycloakRoute,
                    'oauth_azure_logout_route'    => '', // No Azure logout
                    default                       => $default,
                };
            });

        // Mock CustomerService to return a specific subdomain
        $mockCustomer = $this->createMock(\demosplan\DemosPlanCoreBundle\Entity\User\Customer::class);
        $mockCustomer->method('getSubdomain')->willReturn('test');
        $this->customerService->method('getCurrentCustomer')->willReturn($mockCustomer);

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

    private function createMockRedirectResponse(): MockObject
    {
        $mockRedirectResponse = $this->createMock(RedirectResponse::class);
        $mockRedirectResponse->headers = $this->createMock(ResponseHeaderBag::class);
        $mockRedirectResponse->headers->method('clearCookie')->willReturn(null);

        return $mockRedirectResponse;
    }
}
