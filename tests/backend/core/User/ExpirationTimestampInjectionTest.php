<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakLogoutManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Base\FunctionalTestCase;

class ExpirationTimestampInjectionTest extends FunctionalTestCase
{
    private ?User $testUser;
    private ?Session $session;
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a parameter bag with the session_lifetime_seconds parameter
        $parameterBag = new ParameterBag([
            'session_lifetime_seconds'    => 7200,
            'oauth_keycloak_logout_route' => '',
        ]);

        // Create the service manually with our parameter bag
        $this->sut = new OzgKeycloakLogoutManager(
            self::getContainer()->get(KernelInterface::class),
            self::getContainer()->get(LoggerInterface::class),
            self::getContainer()->get(CurrentUserService::class),
            self::getContainer()->get(CustomerService::class),
            $parameterBag
        );

        $this->session = new Session(new MockArraySessionStorage());

        $this->testUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($this->testUser);
        $this->enablePermissions(['feature_auto_logout_warning']);
    }

    public function testInjectTokenExpirationIntoSession(): void
    {
        $this->assertFalse($this->session->has(OzgKeycloakLogoutManager::EXPIRATION_TIMESTAMP));

        $this->sut->injectTokenExpirationIntoSession($this->session, $this->testUser);

        $this->assertTrue($this->session->has(OzgKeycloakLogoutManager::EXPIRATION_TIMESTAMP));
        $expirationTimestamp = $this->session->get(OzgKeycloakLogoutManager::EXPIRATION_TIMESTAMP);

        $this->assertIsInt($expirationTimestamp);
        $this->assertGreaterThan(time(), $expirationTimestamp);
    }

    public function testInjectTokenExpirationSkipsWhenAlreadyPresent(): void
    {
        $originalTimestamp = time() + 3600;
        $this->session->set(OzgKeycloakLogoutManager::EXPIRATION_TIMESTAMP, $originalTimestamp);

        $this->sut->injectTokenExpirationIntoSession($this->session, $this->testUser);

        $this->assertEquals($originalTimestamp, $this->session->get(OzgKeycloakLogoutManager::EXPIRATION_TIMESTAMP));
    }

    public function testHasValidTokenWithValidToken(): void
    {
        $futureTimestamp = time() + 3600; // 1 hour from now
        $this->session->set(OzgKeycloakLogoutManager::EXPIRATION_TIMESTAMP, $futureTimestamp);

        $result = $this->sut->hasValidToken($this->session);

        $this->assertTrue($result);
    }

    public function testHasValidTokenWithExpiredToken(): void
    {
        $pastTimestamp = time() - 3600; // 1 hour ago
        $this->session->set(OzgKeycloakLogoutManager::EXPIRATION_TIMESTAMP, $pastTimestamp);

        $result = $this->sut->hasValidToken($this->session);

        $this->assertFalse($result);
    }

    public function testHasValidTokenReturnsFalseWhenNoExpirationFound(): void
    {
        $result = $this->sut->hasValidToken($this->session);

        $this->assertFalse($result);
    }

    public function testStoreTokenAndExpirationInSession(): void
    {
        $tokenValues = [
            'id_token'     => 'test_token_value',
            'access_token' => 'access_token_value',
        ];

        $this->sut->storeTokenAndExpirationInSession($this->session, $tokenValues);

        $this->assertTrue($this->session->has(OzgKeycloakLogoutManager::KEYCLOAK_TOKEN));
        $this->assertEquals('test_token_value', $this->session->get(OzgKeycloakLogoutManager::KEYCLOAK_TOKEN));
    }

    public function testStoreTokenAndExpirationInSessionWithoutIdToken(): void
    {
        $tokenValues = [
            'access_token' => 'access_token_value',
        ];

        $this->sut->storeTokenAndExpirationInSession($this->session, $tokenValues);

        $this->assertFalse($this->session->has(OzgKeycloakLogoutManager::KEYCLOAK_TOKEN));
    }
}
