<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Symfony\Component\HttpFoundation\Request;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Cookie\PreviousRouteCookie;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Throwable;

class ExceptionService
{
    public function __construct(private readonly RequestStack $requestStack, private readonly RouterInterface $router, private readonly MessageBagInterface $messageBag, private readonly LoggerInterface $logger)
    {
    }

    /**
     * Fehlerbehandlung.
     *
     * @return RedirectResponse|Response Response
     */
    public function handleError(Throwable $e)
    {
        $logger = $this->logger;
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            $logger->warning('Exception occured without request');
            $logger->info($e);

            return $this->redirectToRoute('core_home');
        }
        // Fehlertemplate ausgeben
        if ($e instanceof SessionUnavailableException) {
            $logger->info($e);
            return $this->redirectWithCurrentRouteState('sessionExpired');
        }
        if ($e instanceof AccessDeniedGuestException) {
            $logger->info($e);
            return $this->redirectWithCurrentRouteState('accessdenied');
        }
        if ($e instanceof AccessDeniedException) {
            $logger->warning($e);
            // do not set redirect LoggedIn Route Cookie as it may lead to
            // infinite redirects
            return $this->redirectWithCurrentRouteState('accessdenied', false);
        }
        if ($e instanceof EntityNotFoundException) {
            $logger->error($e);
            $procedureId = $request->attributes->get('procedureId');
            return $this->redirectToRoute('dplan_assessmenttable_view_table', ['procedureId' => $procedureId]);
        }
        $logger->error($e);
        // Login fehlgeschlagen
        if (1004 === $e->getCode()) {
            try {
                $this->messageBag->add('warning', 'warning.login.failed');
            } catch (MessageBagException) {
                $this->logger->warning('Could not add Message to message bag');
            }

            return $this->redirectToRoute('core_home');
        }
        return $this->redirectToRoute('core_500');
    }

    /**
     * @param string $route
     * @param int    $status
     */
    protected function redirectToRoute($route, array $parameters = [], $status = 302): RedirectResponse
    {
        return new RedirectResponse($this->router->generate($route, $parameters), $status);
    }

    public function create404Response(): RedirectResponse
    {
        return $this->redirectToRoute(
            'core_404',
            ['currentPage' => $this->router->getContext()->getPathInfo()]
        );
    }

    /**
     * Create RedirectResponse and save current route for later redirecting.
     *
     * @param string $statusHash
     * @param bool   $setRedirectLoggedInRouteCookie
     */
    protected function redirectWithCurrentRouteState($statusHash, $setRedirectLoggedInRouteCookie = true): RedirectResponse
    {
        $request = $this->requestStack->getCurrentRequest();
        $url = $this->router->generate(
            'core_home',
            [
                'status' => $statusHash,
            ]
        );
        $redirect = new RedirectResponse($url);

        if ($setRedirectLoggedInRouteCookie) {
            // save current route in cookie for later redirecting
            $redirect->headers->setCookie(PreviousRouteCookie::create($request));
        }

        return $redirect;
    }
}
