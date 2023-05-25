<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Cookie\PreviousRouteCookie;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\Platform\EntryPointDecider;
use demosplan\DemosPlanCoreBundle\Logic\Platform\EntryPointDeciderInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PublicIndexProcedureLister;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\EntrypointRoute;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;

use const ARRAY_FILTER_USE_KEY;

class EntrypointController extends BaseController
{
    /**
     * @var string
     */
    private const SESSION_REDIRECT_BREAKER_NAME = 'redirected';

    /**
     * @var CurrentUserInterface
     */
    private $currentUserService;

    /**
     * @var EntryPointDecider
     */
    private $entryPointDecider;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        CurrentUserInterface $currentUserService,
        EntryPointDeciderInterface $entryPointDeciderService,
        RouterInterface $router
    ) {
        $this->currentUserService = $currentUserService;
        $this->entryPointDecider = $entryPointDeciderService;
        $this->router = $router;
    }

    /**
     * Handle index redirects for logged in users.
     *
     * Logged in users get different index pages depending on their role or
     * role combination. Guests that end up here will be redirected to
     * the platform's external start page.
     *
     * @Route(path="/loggedin", name="core_home_loggedin")
     *
     * @DplanPermissions("area_demosplan")
     */
    public function loggedInIndexEntrypointAction(Request $request): Response
    {
        // check whether user tried to call route before login
        if (!$this->isAlreadyRedirected($request) && $request->cookies->has(PreviousRouteCookie::NAME)) {
            try {
                $redirectPath = $request->cookies->get(PreviousRouteCookie::NAME);
                $routeInfo = $this->router->match($redirectPath);

                $parameters = array_filter($routeInfo, static function (string $key) {
                    // filter out non-parameter info keys
                    return '_' !== $key[0];
                }, ARRAY_FILTER_USE_KEY);

                $redirectToRoute = $this->redirectToRoute($routeInfo['_route'], $parameters);
                $redirectToRoute->headers->clearCookie(PreviousRouteCookie::NAME);

                $this->setIsAlreadyRedirected($request);

                return $redirectToRoute;
            } catch (ResourceNotFoundException $exception) {
                /**
                 * If no resource can be matched by the router the path in the cookie
                 * may have been tinkered with. Technically, nothing needs to happen here,
                 * but it's a good idea to clear the cookie and start over again.
                 */
                $redirectToSelf = $this->redirectToRoute('core_home_loggedin');
                $redirectToSelf->headers->clearCookie(PreviousRouteCookie::NAME);

                $this->setIsAlreadyRedirected($request);

                return $redirectToSelf;
            }
        }

        $this->removeIsAlreadyRedirected($request);

        $user = $this->currentUserService->getUser();

        // redirect to public index on session failures
        if (!$user instanceof User) {
            return $this->forward('demosplan\DemosPlanCoreBundle\Controller\Platform\EntrypointController::indexAction');
        }

        $entrypointRoute = $this->entryPointDecider->determineEntryPointForUser($user);

        return $this->processEntrypointRoute($entrypointRoute);
    }

    private function isAlreadyRedirected(Request $request): bool
    {
        $redirected = $request->getSession()->get(self::SESSION_REDIRECT_BREAKER_NAME, 0);

        return 1 === $redirected;
    }

    private function setIsAlreadyRedirected(Request $request): void
    {
        // save redirection to Session to avoid eternal redirection as cookie is not deleted immediately
        $request->getSession()->set(self::SESSION_REDIRECT_BREAKER_NAME, 1);
    }

    private function removeIsAlreadyRedirected(Request $request): void
    {
        $request->getSession()->remove(self::SESSION_REDIRECT_BREAKER_NAME);
    }

    /**
     * Determine the platform's start page.
     *
     * The start page of a demosplan instance is configurable to
     * match different project needs. If a logged in user
     * ends up at this page, they may need to be re-routed to
     * their designated logged-in index page.
     *
     * @Route(path="/", name="core_home", options={"expose": true})
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    public function indexAction(
        ContentService $contentService,
        PublicIndexProcedureLister $procedureLister,
        Request $request
    ) {
        if ($this->currentUserService->hasPermission('area_public_participation')) {
            return $this->renderPublicIndexList(
                $contentService,
                $procedureLister,
                $request
            );
        }

        $entrypointRoute = $this->entryPointDecider->determinePublicEntrypoint();

        return $this->processEntrypointRoute($entrypointRoute);
    }

    /**
     * @return RedirectResponse|Response
     */
    protected function processEntrypointRoute(EntrypointRoute $entrypointRoute)
    {
        if (false === $entrypointRoute->getDoRedirect()) {
            return $this->forward(
                $entrypointRoute->getController(),
                $entrypointRoute->getParameters()
            );
        }

        // redirect might be a route or a url
        if ($entrypointRoute->redirectLeavesPlatform()) {
            return $this->redirect($entrypointRoute->getRoute());
        }

        return $this->redirectToRoute(
            $entrypointRoute->getRoute(),
            $entrypointRoute->getParameters()
        );
    }

    /**
     * Public index start page template.
     *
     * @param string $title Must be empty instead of null to allow
     *                      URL generation without $orgaSlug somewhere
     *                      else in the application
     *
     * @return RedirectResponse|Response|null
     *
     * @throws Exception
     */
    protected function renderPublicIndexList(
        ContentService $contentService,
        PublicIndexProcedureLister $procedureLister,
        Request $request
    ) {
        $templateVars = $procedureLister->getPublicIndexProcedureList($request);
        $templateVars = $procedureLister->reformatPhases(
            $this->currentUserService->getUser()->isLoggedIn(),
            $templateVars
        );

        // Füge die letzten aktuellen Mitteilungen hinzu
        $templateVars['list']['newslist'] = [];

        try {
            $globalNews = $contentService->getContentList($this->currentUserService->getUser(), 3);

            $templateVars['list']['newslist'] = $globalNews;
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not add News to public index: ', [$e]);
        }

        $templateVars['participatedProcedures'] = [];
        $userMarkedParticipated = $contentService->getSettings(
            'markedParticipated',
            SettingsFilter::whereUser($this->currentUserService->getUser())->lock()
        );

        foreach ($userMarkedParticipated as $setting) {
            $templateVars['participatedProcedures'][] = $setting['procedureId'];
        }

        $user = $this->currentUserService->getUser();
        $templateVars['publicUser'] = $user->isPublicUser();
        $templateVars['publicAgency'] = $user->isPublicAgency();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/public_index.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.public.participation',
                'gatewayURL'   => $this->globalConfig->getGatewayURL(),
            ]
        );
    }
}
