<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Security;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\LoginFormAuthenticator;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Tests\Base\UnitTestCase;

class LoginFormAuthenticatorTest extends UnitTestCase
{
    private const MATCHING_SUBDOMAIN = 'idp-only';
    private const OTHER_SUBDOMAIN = 'mixed';
    private const IDP_USER_LOGIN = 'idp-user@example.test';
    private const LOCAL_USER_LOGIN = 'local-user@example.test';
    private const CREDENTIAL_LOGIN = 'whatever@example.test';

    private ?MockObject $userMapper = null;
    private ?MockObject $customerService = null;
    private ?MockObject $logger = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userMapper = $this->createMock(UserMapperInterface::class);
        $this->customerService = $this->createMock(CustomerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * @throws ReflectionException
     */
    public function testRejectsIdpUserWhenSubdomainIsIdpOnly(): void
    {
        $sut = $this->buildSut([self::MATCHING_SUBDOMAIN]);

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);
        $user->method('getLogin')->willReturn(self::IDP_USER_LOGIN);

        $this->userMapper->method('getValidUser')->willReturn($user);
        $this->customerService->method('getCurrentCustomer')
            ->willReturn($this->customerWithSubdomain(self::MATCHING_SUBDOMAIN));

        $this->expectException(AuthenticationException::class);
        $this->invokeGetPassport($sut, $this->credentials());
    }

    /**
     * @throws ReflectionException
     */
    public function testAllowsIdpUserWhenSubdomainIsNotInList(): void
    {
        $sut = $this->buildSut([self::MATCHING_SUBDOMAIN]);

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);
        $user->method('getLogin')->willReturn(self::IDP_USER_LOGIN);

        $this->userMapper->method('getValidUser')->willReturn($user);
        $this->customerService->method('getCurrentCustomer')
            ->willReturn($this->customerWithSubdomain(self::OTHER_SUBDOMAIN));

        $passport = $this->invokeGetPassport($sut, $this->credentials());

        self::assertPassportBuiltForLogin($passport, self::IDP_USER_LOGIN);
    }

    /**
     * @throws ReflectionException
     */
    public function testAllowsLocalUserWhenSubdomainIsIdpOnly(): void
    {
        $sut = $this->buildSut([self::MATCHING_SUBDOMAIN]);

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(false);
        $user->method('getLogin')->willReturn(self::LOCAL_USER_LOGIN);

        $this->userMapper->method('getValidUser')->willReturn($user);
        // Customer lookup is irrelevant for non-IdP users but may still be invoked; allow either.
        $this->customerService->method('getCurrentCustomer')
            ->willReturn($this->customerWithSubdomain(self::MATCHING_SUBDOMAIN));

        $passport = $this->invokeGetPassport($sut, $this->credentials());

        self::assertPassportBuiltForLogin($passport, self::LOCAL_USER_LOGIN);
    }

    /**
     * @throws ReflectionException
     */
    public function testEmptyConfigSkipsCustomerLookup(): void
    {
        $sut = $this->buildSut([]);

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);
        $user->method('getLogin')->willReturn(self::IDP_USER_LOGIN);

        $this->userMapper->method('getValidUser')->willReturn($user);
        $this->customerService->expects(self::never())->method('getCurrentCustomer');

        $passport = $this->invokeGetPassport($sut, $this->credentials());

        self::assertPassportBuiltForLogin($passport, self::IDP_USER_LOGIN);
    }

    /**
     * @throws ReflectionException
     */
    public function testCustomerLookupFailureDoesNotBlockLogin(): void
    {
        $sut = $this->buildSut([self::MATCHING_SUBDOMAIN]);

        $user = $this->createMock(User::class);
        $user->method('isProvidedByIdentityProvider')->willReturn(true);
        $user->method('getLogin')->willReturn(self::IDP_USER_LOGIN);

        $this->userMapper->method('getValidUser')->willReturn($user);
        $this->customerService->method('getCurrentCustomer')
            ->willThrowException(new CustomerNotFoundException('no customer'));

        $passport = $this->invokeGetPassport($sut, $this->credentials());

        self::assertPassportBuiltForLogin($passport, self::IDP_USER_LOGIN);
    }

    /**
     * @param list<string> $idpOnlySubdomains
     */
    private function buildSut(array $idpOnlySubdomains): LoginFormAuthenticator
    {
        return new LoginFormAuthenticator(
            $this->userMapper,
            $this->logger,
            $this->createMock(MessageBagInterface::class),
            $this->createMock(TraceableEventDispatcher::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(UserService::class),
            $this->customerService,
            $idpOnlySubdomains,
        );
    }

    private function credentials(): Credentials
    {
        $credentials = new Credentials();
        $credentials->setLogin(self::CREDENTIAL_LOGIN);
        $credentials->setPassword('secret');
        $credentials->setToken('csrf');
        $credentials->lock();

        return $credentials;
    }

    private function customerWithSubdomain(string $subdomain): CustomerInterface
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getSubdomain')->willReturn($subdomain);

        return $customer;
    }

    /**
     * @throws ReflectionException
     */
    private function invokeGetPassport(LoginFormAuthenticator $sut, Credentials $credentials): Passport
    {
        return (new ReflectionMethod($sut, 'getPassport'))->invoke($sut, $credentials);
    }

    private static function assertPassportBuiltForLogin(Passport $passport, string $expectedLogin): void
    {
        /** @var UserBadge $userBadge */
        $userBadge = $passport->getBadge(UserBadge::class);
        self::assertNotNull($userBadge, 'Passport must carry a UserBadge');
        self::assertSame($expectedLogin, $userBadge->getUserIdentifier());
    }
}
