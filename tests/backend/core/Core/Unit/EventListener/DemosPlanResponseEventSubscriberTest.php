<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\User\ProcedureIntegrationUser;
use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\DemosPlanResponseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\TransformMessageBagService;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\SecurityUserProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class DemosPlanResponseEventSubscriberTest extends TestCase
{
    private SecurityUserProvider&MockObject $securityUserProvider;
    private TokenStorage $tokenStorage;
    private ?DemosPlanResponseEventSubscriber $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityUserProvider = $this->createMock(SecurityUserProvider::class);
        $this->tokenStorage = new TokenStorage();

        $this->sut = new DemosPlanResponseEventSubscriber(
            $this->securityUserProvider,
            $this->tokenStorage,
            $this->createMock(TransformMessageBagService::class)
        );
    }

    /**
     * A functional user has no row to look up, so swapping it turned a successful response into a 401.
     */
    public function testFunctionalUserIsLeftInTheTokenUntouched(): void
    {
        $integrationUser = new ProcedureIntegrationUser();
        $this->tokenStorage->setToken(
            new PostAuthenticationToken($integrationUser, 'api', ['ROLE_USER'])
        );

        $this->securityUserProvider->expects(self::never())->method('getSecurityUser');

        $this->sut->onKernelResponse($this->createResponseEvent());

        self::assertSame($integrationUser, $this->tokenStorage->getToken()->getUser());
    }

    public function testDatabaseBackedUserIsSwappedForItsSecurityUser(): void
    {
        $user = $this->createDatabaseBackedUser();
        $this->tokenStorage->setToken(
            new PostAuthenticationToken($user, 'main', ['ROLE_USER'])
        );

        $securityUser = new SecurityUser($user);
        $this->securityUserProvider->expects(self::once())
            ->method('getSecurityUser')
            ->with('planner@example.org')
            ->willReturn($securityUser);

        $this->sut->onKernelResponse($this->createResponseEvent());

        self::assertSame($securityUser, $this->tokenStorage->getToken()->getUser());
    }

    public function testAlreadySwappedUserIsNotLookedUpAgain(): void
    {
        $securityUser = new SecurityUser($this->createDatabaseBackedUser());
        $this->tokenStorage->setToken(
            new PostAuthenticationToken($securityUser, 'main', ['ROLE_USER'])
        );

        $this->securityUserProvider->expects(self::never())->method('getSecurityUser');

        $this->sut->onKernelResponse($this->createResponseEvent());

        self::assertSame($securityUser, $this->tokenStorage->getToken()->getUser());
    }

    private function createDatabaseBackedUser(): User
    {
        $user = new User();
        $user->setId('c0ffee00-0000-4000-8000-000000000001');
        $user->setLogin('planner@example.org');

        return $user;
    }

    /**
     * A plain 200 keeps the subscriber's message-bag handling inert.
     */
    private function createResponseEvent(): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/api/1.0/procedure-integration/recommendations', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response('', Response::HTTP_OK)
        );
    }
}
