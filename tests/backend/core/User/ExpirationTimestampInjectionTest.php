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

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Base\FunctionalTestCase;

class ExpirationTimestampInjectionTest extends FunctionalTestCase
{
    private ?Session $session;
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $parameterBag = new ParameterBag([
            'session_lifetime_seconds'              => 7200,
            'oauth_keycloak_logout_route'           => '',
            'oauth_token_fast_path_interval_seconds' => 180,
        ]);

        $this->sut = new OzgKeycloakSessionManager(
            self::getContainer()->get(KernelInterface::class),
            self::getContainer()->get(LoggerInterface::class),
            self::getContainer()->get(CurrentUserService::class),
            self::getContainer()->get(CustomerService::class),
            $parameterBag,
            self::getContainer()->get(CustomerOAuthConfigRepository::class),
        );

        $this->session = new Session(new MockArraySessionStorage());

        $testUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($testUser);
    }

    public function testInjectTokenExpirationIntoSession(): void
    {
        $this->assertFalse($this->session->has(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP));

        $this->sut->injectTokenExpirationIntoSession($this->session, 'test-user-id');

        $this->assertTrue($this->session->has(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP));
        $expirationTimestamp = $this->session->get(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP);

        $this->assertIsInt($expirationTimestamp);
        $this->assertGreaterThan(time(), $expirationTimestamp);
    }

    public function testInjectTokenExpirationSkipsWhenAlreadyPresent(): void
    {
        $originalTimestamp = time() + 3600;
        $this->session->set(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP, $originalTimestamp);

        $this->sut->injectTokenExpirationIntoSession($this->session, 'test-user-id');

        $this->assertEquals($originalTimestamp, $this->session->get(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP));
    }

    public function testStoreIdTokenForLogout(): void
    {
        $this->sut->storeIdTokenForLogout($this->session, 'test_id_token_value');

        $this->assertTrue($this->session->has(OzgKeycloakSessionManager::KEYCLOAK_TOKEN));
        $this->assertEquals('test_id_token_value', $this->session->get(OzgKeycloakSessionManager::KEYCLOAK_TOKEN));
    }

    public function testSyncSessionSetsExpirationTimestampToRefreshTokenExpiry(): void
    {
        $accessExpiry = new DateTime('+5 minutes');
        $refreshExpiry = new DateTime('+30 minutes');

        $this->sut->syncSession($this->session, 'test-user-id', $accessExpiry, $refreshExpiry);

        $this->assertTrue($this->session->has(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP));
        $this->assertEquals(
            $refreshExpiry->getTimestamp(),
            $this->session->get(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP)
        );
    }

    public function testSyncSessionSkipsExpirationTimestampWhenLoginOnly(): void
    {
        $this->enablePermissions(['feature_keycloak_used_for_login_only']);

        $accessExpiry = new DateTime('+5 minutes');
        $refreshExpiry = new DateTime('+30 minutes');

        $this->sut->syncSession($this->session, 'test-user-id', $accessExpiry, $refreshExpiry);

        // syncSession must not touch EXPIRATION_TIMESTAMP in login-only mode
        $this->assertFalse($this->session->has(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP));

        // injectTokenExpirationIntoSession sets it to the PHP session lifetime (7200s in test config)
        $this->sut->injectTokenExpirationIntoSession($this->session, 'test-user-id');

        $expirationTimestamp = $this->session->get(OzgKeycloakSessionManager::EXPIRATION_TIMESTAMP);

        // Must reflect the 7200s session lifetime, not the 5-min access or 30-min refresh token
        $this->assertGreaterThan(time() + 3600, $expirationTimestamp);
        $this->assertLessThanOrEqual(time() + 7200, $expirationTimestamp);
    }
}
