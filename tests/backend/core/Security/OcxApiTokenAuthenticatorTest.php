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

use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxApiToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiTokenService;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\OcxApiTokenAuthenticator;
use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Tests\Base\UnitTestCase;

class OcxApiTokenAuthenticatorTest extends UnitTestCase
{
    private ?MockObject $tokenService = null;
    private ?OcxApiTokenAuthenticator $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenService = $this->createMock(OcxApiTokenService::class);
        $this->sut = new OcxApiTokenAuthenticator($this->tokenService);
    }

    public function testValidTokenResolvesItsUserAndCustomer(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getLogin')->willReturn('login');
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('customer-id');
        $apiToken = $this->createMock(OcxApiToken::class);
        $apiToken->method('getUser')->willReturn($user);
        $apiToken->method('getCustomer')->willReturn($customer);
        $this->tokenService->expects(self::once())
            ->method('findActiveToken')
            ->with('dplan_ocx_token')
            ->willReturn($apiToken);
        $request = $this->requestWithAuthorization('Bearer dplan_ocx_token');

        $passport = $this->sut->authenticate($request);

        self::assertSame($user, $passport->getUser());
        self::assertSame('customer-id', $request->attributes->get(OcxApiTokenAuthenticator::CUSTOMER_ID_ATTRIBUTE));
    }

    public function testSuccessAttachesTheOcxContextToTheRequest(): void
    {
        $request = new Request();

        $this->sut->onAuthenticationSuccess($request, $this->createMock(TokenInterface::class), 'ocx');

        self::assertInstanceOf(
            OcxRequestContext::class,
            $request->attributes->get(ApiTokenContextInterface::REQUEST_ATTRIBUTE)
        );
    }

    public static function headersWithoutToken(): array
    {
        return [
            'no header'    => [null],
            'not bearer'   => ['Basic dplan_ocx_token'],
            'empty bearer' => ['Bearer   '],
        ];
    }

    #[DataProvider('headersWithoutToken')]
    public function testRejectsMissingToken(?string $header): void
    {
        $this->tokenService->expects(self::never())->method('findActiveToken');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->sut->authenticate($this->requestWithAuthorization($header));
    }

    public function testRejectsTokenWithoutUser(): void
    {
        $this->tokenService->method('findActiveToken')->willReturn(null);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->sut->authenticate($this->requestWithAuthorization('Bearer dplan_ocx_token'));
    }

    /**
     * @throws JsonException
     */
    public function testFailureAndEntryPointAnswerWithProblemJson(): void
    {
        $responses = [
            $this->sut->onAuthenticationFailure(new Request(), new AuthenticationException()),
            $this->sut->start(new Request()),
        ];

        foreach ($responses as $response) {
            self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
            self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
            $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(Response::HTTP_UNAUTHORIZED, $body['status']);
            self::assertSame('Unauthorized', $body['title']);
        }
    }

    private function requestWithAuthorization(?string $header): Request
    {
        $request = new Request();
        if (null !== $header) {
            $request->headers->set('Authorization', $header);
        }

        return $request;
    }
}
