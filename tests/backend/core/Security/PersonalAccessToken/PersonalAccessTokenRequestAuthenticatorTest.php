<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Security\PersonalAccessToken;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenRequestAuthenticator;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\UnitTestCase;

class PersonalAccessTokenRequestAuthenticatorTest extends UnitTestCase
{
    private ?PersonalAccessTokenRequestAuthenticator $sut = null;
    private ?MockObject $tokenService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenService = $this->createMock(PersonalAccessTokenService::class);
        $this->sut = new PersonalAccessTokenRequestAuthenticator(
            $this->tokenService,
            new NullLogger(),
        );
    }

    public function testSupportsReturnsFalseForMissingHeader(): void
    {
        self::assertFalse($this->sut->supports(new Request()));
    }

    public function testSupportsReturnsFalseForNonBearerAuthorization(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        self::assertFalse($this->sut->supports($request));
    }

    public function testSupportsReturnsFalseForJwtBearer(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer eyJhbGciOiJIUzI1NiJ9.foo.bar');

        self::assertFalse($this->sut->supports($request));
    }

    public function testSupportsReturnsTrueForDplanPatBearer(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.PersonalAccessToken::TOKEN_LITERAL_PREFIX.'abcdefghijklmnop');

        self::assertTrue($this->sut->supports($request));
    }

    public function testAuthenticateReturnsNullWhenTokenUnknown(): void
    {
        $this->tokenService->method('findByPlaintext')->willReturn(null);
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.PersonalAccessToken::TOKEN_LITERAL_PREFIX.'abcdefghijklmnop');

        self::assertNull($this->sut->authenticate($request));
        self::assertFalse($request->attributes->has(PersonalAccessTokenContext::REQUEST_ATTRIBUTE));
    }

    public function testAuthenticateReturnsNullWhenOwnerIsDeleted(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isDeleted')->willReturn(true);

        $token = $this->createMock(PersonalAccessToken::class);
        $token->method('getUser')->willReturn($user);
        $token->method('getTokenPrefix')->willReturn('abcdefghijkl');

        $this->tokenService->method('findByPlaintext')->willReturn($token);
        $this->tokenService->expects(self::never())->method('markUsed');

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.PersonalAccessToken::TOKEN_LITERAL_PREFIX.'abcdefghijklmnop');

        self::assertNull($this->sut->authenticate($request));
        self::assertFalse($request->attributes->has(PersonalAccessTokenContext::REQUEST_ATTRIBUTE));
    }

    public function testAuthenticateReturnsUserAndAttachesContextOnSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isDeleted')->willReturn(false);
        $user->method('getId')->willReturn('user-1');

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('customer-1');

        $token = new PersonalAccessToken(
            user: $user,
            customer: $customer,
            name: 'test',
            tokenPrefix: 'abcdefghijkl',
            tokenHash: 'hashed',
            scopes: [PersonalAccessTokenScope::STATEMENTS_READ],
            expiresAt: new DateTime('+30 days'),
        );

        $this->tokenService->method('findByPlaintext')->willReturn($token);
        $this->tokenService->expects(self::once())->method('markUsed')->with($token);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.PersonalAccessToken::TOKEN_LITERAL_PREFIX.'abcdefghijklmnop');

        $authenticated = $this->sut->authenticate($request);

        self::assertSame($user, $authenticated);
        $context = $request->attributes->get(PersonalAccessTokenContext::REQUEST_ATTRIBUTE);
        self::assertInstanceOf(PersonalAccessTokenContext::class, $context);
        self::assertSame($token, $context->token);
        self::assertContains('feature_json_api_list', $context->scopePermissions);
    }
}
