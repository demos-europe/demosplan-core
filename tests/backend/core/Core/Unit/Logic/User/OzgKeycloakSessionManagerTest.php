<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class OzgKeycloakSessionManagerTest extends TestCase
{
    private ?OzgKeycloakSessionManager $sut = null;

    protected function setUp(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getSubdomain')->willReturn('hh');

        $customerService = $this->createMock(CustomerService::class);
        $customerService->method('getCurrentCustomer')->willReturn($customer);

        $this->sut = new OzgKeycloakSessionManager(
            $this->createMock(KernelInterface::class),
            $this->createMock(LoggerInterface::class),
            $customerService,
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(CustomerOAuthConfigRepository::class),
        );
    }

    public function testGetLogoutUrlInjectsSubdomainIntoRedirectUri(): void
    {
        $logoutRoute = 'https://auth.example.com/logout?post_logout_redirect_uri=https://beteiligung.example.com/connect/keycloak_ozg&client_id=client&id_token_hint=';

        $result = $this->sut->getLogoutUrl($logoutRoute, 'token123');

        self::assertSame(
            'https://auth.example.com/logout?post_logout_redirect_uri=https://hh.beteiligung.example.com/connect/keycloak_ozg&client_id=client&id_token_hint=token123',
            $result
        );
    }

    public function testGetLogoutUrlDoesNotDoubleSubdomainWhenAlreadyPresent(): void
    {
        $logoutRoute = 'https://auth.example.com/logout?post_logout_redirect_uri=https://hh.beteiligung.example.com/connect/keycloak_ozg&client_id=client&id_token_hint=';

        $result = $this->sut->getLogoutUrl($logoutRoute, 'token123');

        self::assertSame(
            'https://auth.example.com/logout?post_logout_redirect_uri=https://hh.beteiligung.example.com/connect/keycloak_ozg&client_id=client&id_token_hint=token123',
            $result
        );
    }

    public function testGetLogoutUrlInjectsSubdomainWhenDomainSharesSubdomainLetters(): void
    {
        // host starts with the same letters as the subdomain but is not subdomain-qualified
        $logoutRoute = 'https://auth.example.com/logout?post_logout_redirect_uri=https://hhsomething.example.com/connect/keycloak_ozg&client_id=client&id_token_hint=';

        $result = $this->sut->getLogoutUrl($logoutRoute, null);

        self::assertSame(
            'https://auth.example.com/logout?post_logout_redirect_uri=https://hh.hhsomething.example.com/connect/keycloak_ozg&client_id=client&id_token_hint=',
            $result
        );
    }
}
