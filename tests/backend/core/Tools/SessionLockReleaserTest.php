<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Tools;

use demosplan\DemosPlanCoreBundle\Tools\SessionLockReleaser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\UnitTestCase;

class SessionLockReleaserTest extends UnitTestCase
{
    public function testReleaseWithoutRequestDoesNothing(): void
    {
        SessionLockReleaser::release(new RequestStack());

        $this->addToAssertionCount(1);
    }

    public function testReleaseSavesSessionOfRealRequest(): void
    {
        [$requestStack, $session] = $this->createStackWithStartedSession(false);

        SessionLockReleaser::release($requestStack);

        self::assertFalse($session->isStarted());
    }

    public function testReleaseSkipsSyntheticRequestAndRepeatedCallsStaySafe(): void
    {
        [$requestStack, $session] = $this->createStackWithStartedSession(true);

        SessionLockReleaser::release($requestStack);
        SessionLockReleaser::release($requestStack);

        self::assertTrue($session->isStarted());
    }

    /**
     * @return array{0: RequestStack, 1: Session}
     */
    private function createStackWithStartedSession(bool $synthetic): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);
        if ($synthetic) {
            SessionLockReleaser::markSynthetic($request);
        }
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return [$requestStack, $session];
    }
}
