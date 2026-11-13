<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SessionLockReleaser
{
    /** Set on the request that message handlers push, which carries no real HTTP session. */
    public const SYNTHETIC_REQUEST_ATTRIBUTE = '_synthetic_message_request';

    public static function markSynthetic(Request $request): void
    {
        $request->attributes->set(self::SYNTHETIC_REQUEST_ATTRIBUTE, true);
    }

    /**
     * Releases the database session row lock before a long blocking call.
     * Skipped without a request and for synthetic message-handler requests.
     */
    public static function release(RequestStack $requestStack): void
    {
        $request = $requestStack->getCurrentRequest();
        if (!$request instanceof Request || $request->attributes->getBoolean(self::SYNTHETIC_REQUEST_ATTRIBUTE)) {
            return;
        }

        $requestStack->getSession()->save();
    }
}
