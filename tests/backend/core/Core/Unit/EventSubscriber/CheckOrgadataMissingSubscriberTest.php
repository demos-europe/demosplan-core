<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\EventSubscriber\CheckOrgadataMissingSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

class CheckOrgadataMissingSubscriberTest extends TestCase
{
    private const WELCOME_ROUTE = 'DemosPlan_user_complete_data';
    private const WELCOME_URL = '/willkommen';
    private const REGULAR_ROUTE = 'some_regular_route';

    private CurrentUserInterface $currentUser;
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->currentUser = $this->createMock(CurrentUserInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')
            ->with(self::WELCOME_ROUTE)
            ->willReturn(self::WELCOME_URL);
    }

    public function testCitizenIsNeverRedirected(): void
    {
        $user = $this->createUser(roles: [RoleInterface::CITIZEN], profileCompleted: false);
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::REGULAR_ROUTE);
        $event->expects($this->never())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    public function testExcludedRouteIsNeverRedirected(): void
    {
        // Coordinator with missing email2 would normally be redirected, but not on the welcome route itself
        $user = $this->createUser(
            roles: [RoleInterface::PUBLIC_AGENCY_COORDINATION],
            profileCompleted: false,
            orga: $this->createOrga(null),
        );
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::WELCOME_ROUTE);
        $event->expects($this->never())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    public function testIncompleteProfileIsRedirected(): void
    {
        $user = $this->createUser(roles: [RoleInterface::PLANNING_AGENCY_ADMIN], profileCompleted: false);
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::REGULAR_ROUTE);
        $event->expects($this->once())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    public function testCoordinatorWithMissingEmail2IsRedirected(): void
    {
        $user = $this->createUser(
            roles: [RoleInterface::PUBLIC_AGENCY_COORDINATION],
            profileCompleted: true,
            orga: $this->createOrga(null),
        );
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::REGULAR_ROUTE);
        $event->expects($this->once())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    public function testCoordinatorWithBlankEmail2IsRedirected(): void
    {
        $user = $this->createUser(
            roles: [RoleInterface::PUBLIC_AGENCY_COORDINATION],
            profileCompleted: true,
            orga: $this->createOrga('   '),
        );
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::REGULAR_ROUTE);
        $event->expects($this->once())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    public function testCoordinatorWithEmail2IsNotRedirected(): void
    {
        $user = $this->createUser(
            roles: [RoleInterface::PUBLIC_AGENCY_COORDINATION],
            profileCompleted: true,
            orga: $this->createOrga('institution@example.org'),
        );
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::REGULAR_ROUTE);
        $event->expects($this->never())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    public function testNonCoordinatorWithMissingEmail2IsNotRedirected(): void
    {
        $user = $this->createUser(
            roles: [RoleInterface::PUBLIC_AGENCY_WORKER],
            profileCompleted: true,
            orga: $this->createOrga(null),
        );
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::REGULAR_ROUTE);
        $event->expects($this->never())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    public function testCoordinatorWithoutOrgaIsNotRedirected(): void
    {
        $user = $this->createUser(
            roles: [RoleInterface::PUBLIC_AGENCY_COORDINATION],
            profileCompleted: true,
        );
        $this->currentUser->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent(self::REGULAR_ROUTE);
        $event->expects($this->never())->method('setResponse');

        $this->createSubscriber()->onKernelRequest($event);
    }

    private function createSubscriber(): CheckOrgadataMissingSubscriber
    {
        return new CheckOrgadataMissingSubscriber(
            $this->currentUser,
            $this->createMock(LoggerInterface::class),
            $this->router,
        );
    }

    /**
     * @param array<int, string> $roles
     */
    private function createUser(array $roles, bool $profileCompleted, ?OrgaInterface $orga = null): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getName')->willReturn('Test User');
        $user->method('isProfileCompleted')->willReturn($profileCompleted);
        $user->method('getOrga')->willReturn($orga);
        $user->method('hasRole')->willReturnCallback(
            static fn ($role): bool => in_array($role, $roles, true)
        );

        return $user;
    }

    private function createOrga(?string $email2): OrgaInterface
    {
        $orga = $this->createMock(OrgaInterface::class);
        $orga->method('getEmail2')->willReturn($email2);

        return $orga;
    }

    private function createRequestEvent(string $route): RequestEvent&MockObject
    {
        $request = Request::create('/test');
        $request->attributes->set('_route', $route);

        $event = $this->createMock(RequestEvent::class);
        $event->method('getRequest')->willReturn($request);

        return $event;
    }
}
