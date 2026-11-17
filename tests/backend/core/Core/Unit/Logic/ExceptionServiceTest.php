<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Cookie\PreviousRouteCookie;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use demosplan\DemosPlanCoreBundle\Logic\ExceptionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseCookieValueSame;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHasCookie;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ExceptionServiceTest extends TestCase
{
    private const REQUESTED_PATH = '/xlsx-export/geplant/45f445bd-0a45-4d09-9050-76137337718d';

    private ?ExceptionService $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $request = Request::create('https://example.test'.self::REQUESTED_PATH);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/?status=accessdenied');

        $this->sut = new ExceptionService(
            $requestStack,
            $router,
            $this->createMock(MessageBagInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testGuestAccessDeniedStoresRequestedPathInPreviousRouteCookie(): void
    {
        $response = $this->sut->handleError(new AccessDeniedGuestException('Access denied'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertThat($response, new ResponseCookieValueSame(PreviousRouteCookie::NAME, self::REQUESTED_PATH));
    }

    public function testNonGuestAccessDeniedDoesNotStorePreviousRouteCookie(): void
    {
        $response = $this->sut->handleError(new AccessDeniedException('Access denied'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertThat($response, self::logicalNot(new ResponseHasCookie(PreviousRouteCookie::NAME)));
    }
}
