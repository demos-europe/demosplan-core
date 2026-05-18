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
 * Tus chunk uploads can run for many seconds (huge files, virus scan).
 * The PDO session handler holds a row-level lock on the session row from
 * read until response, so a long-running tus request blocks every other
 * concurrent request that touches the same session, eventually surfacing
 * as "SQLSTATE[HY000]: 1205 Lock wait timeout exceeded".
 *
 * Closing the session as early as possible flushes pending writes and
 * releases the lock, while still leaving the request able to read the
 * already-loaded session data.
 */
class TusReleaseSessionLockSubscriber implements EventSubscriberInterface
{
    private const TUS_PATH_PREFIX = '/_tus/';

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), self::TUS_PATH_PREFIX)) {
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
        // controller starts processing the upload body.
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 4]],
        ];
    }
}
