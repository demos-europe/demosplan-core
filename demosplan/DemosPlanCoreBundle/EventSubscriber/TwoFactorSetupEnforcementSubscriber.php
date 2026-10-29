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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorSetupEnforcer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Keeps users who still have to set up a second factor on the setup page.
 *
 * Runs on kernel.controller with priority 6: after ConfigurePermissionsListener (7), which
 * initializes the permissions, and before ReadOnlyProcedureRedirectListener (5) and
 * CheckPermissionEventSubscriber (4).
 */
class TwoFactorSetupEnforcementSubscriber extends BaseEventSubscriber
{
    /**
     * The existing profile page "Meine Daten" holds the 2FA setup.
     */
    private const SETUP_PAGE_ROUTE = 'DemosPlan_user_portal';

    /**
     * "Meine Daten", saving it, and the actions of its two-factor block.
     */
    private const SETUP_ROUTES = [
        'DemosPlan_user_2fa_email_enable',
        'DemosPlan_user_2fa_email_send',
        'DemosPlan_user_2fa_enable',
        'DemosPlan_user_edit',
        'DemosPlan_user_portal',
        'DemosPlan_user_qr_code',
    ];

    /**
     * Imprint and data protection stay reachable.
     */
    private const IMPRINT_AND_DATA_PROTECTION_ROUTES = [
        'DemosPlan_misccontent_static_dataprotection',
        'DemosPlan_misccontent_static_imprint',
    ];

    /**
     * The welcome page (CheckOrgadataMissingSubscriber) comes first: a new user completes the profile,
     * then sets up the second factor. Remove these routes to reverse the order.
     */
    private const PROFILE_COMPLETION_ROUTES = [
        'DemosPlan_user_complete_data',
    ];

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly TwoFactorSetupEnforcer $twoFactorSetupEnforcer,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 6],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // A user who set up a factor in the meantime is released
        if ($this->twoFactorSetupEnforcer->completeSetupIfDone()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->twoFactorSetupEnforcer->isEnforced() || $this->isAllowedRoute($request)) {
            return;
        }

        $this->logger->debug('Two-factor setup pending, blocking request', [
            'userId' => $this->currentUser->getUser()->getId(),
            'route'  => $request->attributes->get('_route'),
        ]);

        $response = $this->createResponse($request);
        $event->setController(static fn (): Response => $response);
    }

    private function isAllowedRoute(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route', '');

        return in_array($route, self::SETUP_ROUTES, true)
            || in_array($route, self::IMPRINT_AND_DATA_PROTECTION_ROUTES, true)
            || in_array($route, self::PROFILE_COMPLETION_ROUTES, true);
    }

    private function createResponse(Request $request): Response
    {
        if ($this->isXhrOrApiRequest($request)) {
            return new JsonResponse(
                ['error' => 'Two-factor setup required'],
                Response::HTTP_FORBIDDEN
            );
        }

        return new RedirectResponse($this->router->generate(self::SETUP_PAGE_ROUTE));
    }

    private function isXhrOrApiRequest(Request $request): bool
    {
        if ($request->isXmlHttpRequest() || str_starts_with($request->getPathInfo(), '/api')) {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json') || str_contains($accept, 'application/vnd.api+json');
    }
}
