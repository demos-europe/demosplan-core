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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        // get the current response, if it is already set by another listener
        $response = $event->getResponse();

        if (null === $response) {
            $response = $this->redirectToRoute('core_home');
        }

        // let SAML handle logout when defined. It does no harm when user is logged in locally.
        // Needs to be http://localhost/ as it needs to be some kind of url, and it is defined
        // like this in the parameters_default.yml
        if ('http://localhost/' !== $this->parameterBag->get('saml_idp_slo_url')) {
            $this->logger->info('Redirecting to SAML for logout', [$this->parameterBag->get('saml_idp_slo_url')]);
            $response = $this->redirectToRoute('saml_logout');
        }

        // let oauth identity provider handle logout when defined
        if ('' !== $this->parameterBag->get('oauth_keycloak_logout_route')) {
            $this->logger->info('Redirecting to Keycloak for logout', [$this->parameterBag->get('oauth_keycloak_logout_route')]);
            $response = $this->redirect($this->parameterBag->get('oauth_keycloak_logout_route'));
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
