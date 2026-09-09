<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Some requests run for many seconds while only reading the session (for
 * authentication) and never writing it: tus chunk uploads (huge files, virus
 * scan) and spellcheck checks (blocked on the LanguageTool backend, which can
 * take ~10s under load).
 *
 * The PDO session handler holds a row-level lock on the session row from read
 * until response, so such a long-running request blocks every other concurrent
 * request that touches the same session, eventually surfacing as
 * "SQLSTATE[HY000]: 1205 Lock wait timeout exceeded".
 *
 * Closing the session as early as possible flushes pending writes and releases
 * the lock, while still leaving the request able to read the already-loaded
 * session data.
 */
class ReleaseSessionLockSubscriber implements EventSubscriberInterface
{
    /**
     * Path prefixes of requests that only read the session but may run long.
     */
    private const RELEASE_LOCK_PATH_PREFIXES = [
        '/_tus/',
        '/api/1.0/spellcheck/',
    ];

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $shouldRelease = false;
        foreach (self::RELEASE_LOCK_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $shouldRelease = true;
                break;
            }
        }
        if (!$shouldRelease) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->save();
        }
    }

    public static function getSubscribedEvents(): array
    {
        // Run after the firewall (priority 8) so the session has been
        // loaded and authentication is established, but before the
        // controller starts processing the request.
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 4]],
        ];
    }
}
