<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Shared post-authentication logic for Keycloak authenticators.
 *
 * Handles multi-organisation selection, single-org auto-select,
 * and redirect after successful/failed authentication.
 *
 * Expects these properties on the using class (via constructor injection):
 *
 * @property \Psr\Log\LoggerInterface                                             $logger
 * @property \Symfony\Component\Routing\RouterInterface                           $router
 * @property \demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService $currentOrganisationService
 */
trait KeycloakAuthenticationSuccessTrait
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $request/$firewallName required by AuthenticatorInterface
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($user instanceof User) {
            $organisations = $user->getOrganisations();

            if ($organisations->count() > 1) {
                if ($this->currentOrganisationService->requiresOrganisationSelection($user)) {
                    $this->logger->info('Multi-organisation user requires organisation selection', [
                        'userId'            => $user->getId(),
                        'organisationCount' => $organisations->count(),
                    ]);

                    return new RedirectResponse(
                        $this->router->generate('DemosPlan_user_select_organisation')
                    );
                }

                $this->currentOrganisationService->initializeCurrentOrganisation($user);
            } elseif (1 === $organisations->count()) {
                $singleOrga = $organisations->first();
                if (false !== $singleOrga) {
                    $this->currentOrganisationService->setCurrentOrganisation($user, $singleOrga);
                    $this->logger->info('Single organisation auto-selected', [
                        'userId' => $user->getId(),
                        'orgaId' => $singleOrga->getId(),
                    ]);
                }
            }
        }

        return new RedirectResponse($this->router->generate('core_home_loggedin'));
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $request required by AuthenticatorInterface
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Keycloak login failed', ['exception' => $exception]);

        return new RedirectResponse($this->router->generate('core_login_idp_error'));
    }
}
