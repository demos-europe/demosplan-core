<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\PendingRequestCacheService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Controller for multi-responsibility organisation selection.
 *
 * Handles the organisation selection page shown after login when a user
 * belongs to multiple organisations, and the organisation switching functionality.
 */
class OrganisationSelectionController extends BaseController
{
    private const SESSION_KEY_RETURN_URL = 'organisation_selection_return_url';

    public function __construct(
        private readonly CurrentOrganisationService $currentOrganisationService,
        private readonly PendingRequestCacheService $pendingRequestCacheService,
    ) {
    }

    /**
     * Display organisation selection page for multi-responsibility users.
     */
    #[DplanPermissions('area_demosplan')]
    #[Route(name: 'DemosPlan_user_select_organisation', path: '/organisation/select')]
    public function selectOrganisation(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('core_home');
        }

        $organisations = $user->getOrganisations();

        // If user has only one org, auto-select and redirect
        if ($organisations->count() <= 1) {
            $singleOrga = $organisations->first();
            if ($singleOrga instanceof Orga) {
                $this->currentOrganisationService->setCurrentOrganisation($user, $singleOrga);
            }

            return $this->redirectToRoute('core_home_loggedin');
        }

        $pendingRequest = $this->pendingRequestCacheService->retrieve($user->getId());
        $pendingOrganisationId = $pendingRequest?->getSelectedOrganisationId();

        // Store validated return URL in session instead of round-tripping through form.
        // Skip during re-auth flow — referer would be the Keycloak callback URL, not a useful destination.
        $referer = $request->headers->get('referer');
        if (null !== $referer && '' !== $referer && null === $pendingOrganisationId) {
            $path = parse_url($referer, PHP_URL_PATH);
            $selectPath = $this->generateUrl('DemosPlan_user_select_organisation');
            if (is_string($path) && 1 === preg_match('#^/[^/]#', $path) && $path !== $selectPath) {
                $request->getSession()->set(self::SESSION_KEY_RETURN_URL, $path);
            }
        }

        return $this->render(
            '@DemosPlanCore/DemosPlanUser/select_organisation.html.twig',
            [
                'title'                      => 'organisation.select',
                'organisations'              => $organisations,
                'currentOrganisationId'      => $user->getCurrentOrganisation()?->getId(),
                'lastSelectedOrganisationId' => $pendingOrganisationId,
            ]
        );
    }

    /**
     * Handle organisation selection/switch.
     */
    #[DplanPermissions('area_demosplan')]
    #[Route(name: 'DemosPlan_user_switch_organisation', path: '/organisation/switch-responsibility', methods: ['POST'])]
    public function switchOrganisation(Request $request): RedirectResponse
    {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('switch_organisation', $token)) {
            throw new AccessDeniedException('Invalid CSRF token');
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('core_home');
        }

        $organisationId = $request->request->get('organisation_id');

        if (null === $organisationId) {
            $this->getMessageBag()->add('error', 'error.organisation.selection.missing');

            return $this->redirectToRoute('DemosPlan_user_select_organisation');
        }

        // Find organisation in user's collection
        $selectedOrga = null;
        foreach ($user->getOrganisations() as $orga) {
            if ($orga->getId() === $organisationId) {
                $selectedOrga = $orga;
                break;
            }
        }

        if (!$selectedOrga instanceof Orga) {
            $this->getLogger()->warning('User tried to switch to organisation they do not belong to', [
                'userId'         => $user->getId(),
                'organisationId' => $organisationId,
            ]);
            $this->getMessageBag()->add('error', 'error.organisation.selection.invalid');

            return $this->redirectToRoute('DemosPlan_user_select_organisation');
        }

        $this->currentOrganisationService->setCurrentOrganisation($user, $selectedOrga);

        $this->getLogger()->info('User switched organisation', [
            'userId'           => $user->getId(),
            'organisationId'   => $selectedOrga->getId(),
            'organisationName' => $selectedOrga->getName(),
        ]);

        $this->getMessageBag()->add('confirm', 'confirm.organisation.switched');

        // Re-auth flow: read pending data from cache, redirect to pending page only when same org chosen.
        $pendingRequest = $this->pendingRequestCacheService->retrieve($user->getId());
        $this->pendingRequestCacheService->delete($user->getId());

        if (null !== $pendingRequest) {
            $pendingOrgId = $pendingRequest->getSelectedOrganisationId();
            $pendingPageUrl = $pendingRequest->getPageUrl();

            if (null !== $pendingOrgId && null !== $pendingPageUrl && $selectedOrga->getId() === $pendingOrgId) {
                // Not yet implemented: if pendingRequest has a buffered POST, redirect to pending request review page
                return new RedirectResponse($pendingPageUrl);
            }
        }

        // Retrieve and clear the return URL from the session
        $session = $request->getSession();
        $returnUrl = $session->get(self::SESSION_KEY_RETURN_URL);
        $session->remove(self::SESSION_KEY_RETURN_URL);

        if (is_string($returnUrl) && '' !== $returnUrl) {
            return new RedirectResponse($returnUrl);
        }

        return $this->redirectToRoute('core_home_loggedin');
    }
}
