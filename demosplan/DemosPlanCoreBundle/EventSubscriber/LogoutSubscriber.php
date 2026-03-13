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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Cookie\PreviousRouteCookie;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakLogoutManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    private array $allowedCookieNames = [PreviousRouteCookie::NAME];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
        private readonly PermissionsInterface $permissions,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OzgKeycloakLogoutManager $ozgKeycloakLogoutManager,
    ) {
    }

    /**
     * Set this listener with priority 1 to execute before Symfony's default LogoutListener:
     *
     * @see \Symfony\Component\Security\Http\EventListener\SessionLogoutListener
     * This prevents the session from being invalidated prematurely,
     * as we need the session to access the stored Keycloak ID token for logout.
     * The token is detected on Keycloak side,
     * enabling silent logout without Keycloak user confirmation dialog.
     *
     * @return array[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => ['onLogout', 1],
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        // get the current response, if it is already set by another listener
        $response = $event->getResponse();

        if (!$response instanceof Response) {
            $response = $this->redirectToRoute('core_home');
        }

        // let oauth identity provider handle logout when defined and user was provided by identity provider
        $user = $event->getToken()?->getUser();
        if ($user && method_exists($user, 'isProvidedByIdentityProvider') && $user->isProvidedByIdentityProvider()) {
            // Keycloak logout
            $logoutRoute = $this->ozgKeycloakLogoutManager->getEffectiveLogoutRoute();
            if (null !== $logoutRoute) {
                $keycloakToken = $event->getRequest()->getSession()->get(OzgKeycloakLogoutManager::KEYCLOAK_TOKEN);
                $event->getRequest()->getSession()->invalidate();

                $this->logger->info('Redirecting to Keycloak for logout initial', [$logoutRoute]);

                // add additional parameters to keycloak logout url for redirect
                try {
                    $logoutRoute = $this->ozgKeycloakLogoutManager->getLogoutUrl($logoutRoute, $keycloakToken);
                    $this->logger->info('Redirecting to Keycloak for logout adjusted', [$logoutRoute]);
                } catch (Exception $e) {
                    $this->logger->error('Could not get current customer', [$e->getMessage()]);
                }
                $response = $this->redirect($logoutRoute);
            }

            // Azure AD logout (Front-Channel logout)
            if ('' !== $this->parameterBag->get('oauth_azure_logout_route')) {
                $logoutRoute = $this->parameterBag->get('oauth_azure_logout_route');
                $this->logger->info('Redirecting to Azure AD for logout initial', [$logoutRoute]);
                $response = $this->redirect($logoutRoute);
            }
        }

        if ($this->permissions->hasPermission('feature_has_logout_landing_page')) {
            $response = $this->redirectToRoute('DemosPlan_user_logout_success');
        }

        // clear dplan Cookies
        foreach ($this->allowedCookieNames as $cookieName) {
            $response->headers->clearCookie($cookieName);
        }

        $event->setResponse($response);
    }

    protected function redirect(string $url): RedirectResponse
    {
        return new RedirectResponse($url);
    }

    protected function redirectToRoute(string $route): RedirectResponse
    {
        return $this->redirect($this->urlGenerator->generate($route));
    }
}
