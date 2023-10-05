<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Cookie\PreviousRouteCookie;
use demosplan\DemosPlanCoreBundle\Event\PreRenderEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Throwable;

class ViewRenderer
{
    public function __construct(private readonly DefaultTwigVariablesService $defaultTwigVariablesService, private readonly MessageBagInterface $messageBag, private readonly RequestStack $requestStack, private readonly TraceableEventDispatcher $traceableEventDispatcher, private readonly RouterInterface $router, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function processRequestParameters(
        string $view,
        array $parameters = [],
        Response $response = null
    ): array {
        $request = $this->getRequest();

        $this->defaultTwigVariablesService->loadVariables($request);

        $parameters = array_merge($this->defaultTwigVariablesService->getVariables(), $parameters);

        $preRenderEvent = new PreRenderEvent($view, $parameters, $response);
        $this->traceableEventDispatcher->post($preRenderEvent);

        return $preRenderEvent->getParameters();
    }

    /**
     * @throws MessageBagException
     */
    public function processRequestStatus(): void
    {
        $request = $this->getRequest();
        // Process status parameter
        if ($request->query->has('status')) {
            switch ($request->query->get('status')) {
                // Session ist abgelaufen
                case 'sessionExpired':
                    $this->messageBag->add('warning', 'warning.session.expired');
                    break;
                    // Zugriff nicht gestattet
                case 'accessdenied':
                    $this->messageBag->add('warning', 'warning.access.denied');
                    break;
                    // Zugriff nicht gestattet
                case 'missingOrgadata':
                    $this->messageBag->add('warning', 'warning.orgadata.missing');
                    break;

                default:
                    break;
            }
        }
    }

    /**
     * Render data as json response.
     *
     * @param int   $appStatus  application status code
     * @param bool  $success    request success indicator
     * @param int   $httpStatus returned http status
     * @param array $headers    additional http headers
     *
     * @deprecated
     *
     * @return JsonResponse
     */
    public function renderJson(array $data, $appStatus = 200, $success = true, $httpStatus = 200, $headers = [])
    {
        // ViewRenderer
        $responseData = [
            'data' => $data,
            'meta' => [
                'code'    => $appStatus,
                'success' => $success,
            ],
        ];

        $headers = array_merge($headers, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);

        $response = new JsonResponse($responseData, $httpStatus, $headers);
        $response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $response;
    }

    /**
     * Fehlerbehandlung.
     *
     * @return RedirectResponse|Response Response
     *
     * @deprecated use DplanPermissions({"permission"}) Annotation on controllers instead
     *     try/catch can be omitted, this error handling has moved to ExceptionListener
     *     and CheckPermissionListener
     *
     * @throws Exception in dev mode only
     */
    public function handleError(Throwable $e): ?Response
    {
        $logger = $this->logger;
        $request = $this->getRequest();

        // Fehlertemplate ausgeben
        switch (true) {
            case $e instanceof SessionUnavailableException:
                $logger->info($e);

                return $this->redirectWithCurrentRouteState('sessionExpired');

            case $e instanceof AccessDeniedGuestException:
                $logger->info($e);

                return $this->redirectWithCurrentRouteState('accessdenied');

            case $e instanceof AccessDeniedException:
                $logger->warning($e);

                // do not set redirect LoggedIn Route Cookie as it may lead to
                // infinite redirects
                return $this->redirectWithCurrentRouteState('accessdenied', false);

            case $e instanceof EntityNotFoundException:
                $logger->error($e);
                $procedureId = $request->attributes->get('procedureId');

                return $this->redirectToRoute('dplan_assessmenttable_view_table', ['procedureId' => $procedureId]);
        }

        return null;
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     *
     * @param string $route      The name of the route
     * @param array  $parameters An array of parameters
     * @param int    $status     The status code to use for the Response
     *
     * @return RedirectResponse
     */
    protected function redirectToRoute($route, array $parameters = [], $status = 302)
    {
        return new RedirectResponse($this->router->generate($route, $parameters), $status);
    }

    /**
     * Create RedirectResponse and save current route for later redirecting.
     *
     * @param string $statusHash
     * @param bool   $setRedirectLoggedInRouteCookie
     *
     * @deprecated as calling method handleError is deprecated as well
     *
     * @return RedirectResponse
     */
    protected function redirectWithCurrentRouteState($statusHash, $setRedirectLoggedInRouteCookie = true)
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

    private function getRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new RuntimeException('Tried to render without a request');
        }

        return $request;
    }
}
