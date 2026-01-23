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
    public function __construct(
        private readonly CurrentOrganisationService $currentOrganisationService,
    ) {
    }

    /**
     * Display organisation selection page for multi-responsibility users.
     */
    #[DplanPermissions('area_demosplan')]
    #[Route(name: 'DemosPlan_user_select_organisation', path: '/organisation/select')]
    public function selectOrganisation(): Response
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

        return $this->render(
            '@DemosPlanCore/DemosPlanUser/select_organisation.html.twig',
            [
                'title' => 'organisation.select',
                'organisations' => $organisations,
                'currentOrganisationId' => $user->getCurrentOrganisation()?->getId(),
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
                'userId' => $user->getId(),
                'organisationId' => $organisationId,
            ]);
            $this->getMessageBag()->add('error', 'error.organisation.selection.invalid');

            return $this->redirectToRoute('DemosPlan_user_select_organisation');
        }

        $this->currentOrganisationService->setCurrentOrganisation($user, $selectedOrga);

        $this->getLogger()->info('User switched organisation', [
            'userId' => $user->getId(),
            'organisationId' => $selectedOrga->getId(),
            'organisationName' => $selectedOrga->getName(),
        ]);

        $this->getMessageBag()->add('confirm', 'confirm.organisation.switched');

        // Redirect to referer if available, otherwise to home
        // Validates: single leading slash followed by non-slash to prevent protocol-relative URLs (//evil.com)
        $referer = $request->request->get('referer');
        if (null !== $referer && '' !== $referer && 1 === preg_match('#^/[^/]#', $referer)) {
            return new RedirectResponse($referer);
        }

        return $this->redirectToRoute('core_home_loggedin');
    }
}
