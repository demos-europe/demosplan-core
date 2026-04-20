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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CryptoException;
use demosplan\DemosPlanCoreBundle\Exception\TokenStorageException;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\PendingRequestCacheService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\ValueObject\PendingRequestData;
use Exception;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Webmozart\Assert\Assert;

/**
 * Shared post-authentication logic for all OZG Keycloak authenticators.
 *
 * Handles pending-request recovery after re-authentication, multi-organisation
 * selection, single-org auto-select, and redirect after successful/failed authentication.
 *
 * Concrete authenticators implement onAuthenticationSuccess() and call
 * handleAuthenticationSuccess() with a real AccessToken or null.
 */
abstract class AbstractOzgKeycloakAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly RouterInterface $router,
        protected readonly CurrentOrganisationService $currentOrganisationService,
        protected readonly MessageBagInterface $messageBag,
        protected readonly OAuthTokenStorageService $oauthTokenStorageService,
        protected readonly PendingRequestCacheService $pendingRequestCacheService,
        protected readonly OzgKeycloakSessionManager $ozgKeycloakSessionManager,
    ) {
    }

    /**
     * Full post-authentication handler shared by all Keycloak authenticators.
     *
     * @param AccessToken|null $accessToken Real OAuth tokens (real authenticator) or null (static authenticator)
     *
     * @throws Exception Bubbles up to onAuthenticationSuccess() where concrete authenticators catch and reroute to onAuthenticationFailure()
     */
    protected function handleAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        ?AccessToken $accessToken,
    ): Response {
        $userId = $request->getSession()->get('userId');
        Assert::notNull($userId, 'userId must be set in session before onAuthenticationSuccess is called');

        $isMultiOrgReauth = false;
        $pendingRequest = $this->readPendingRequest($userId);

        if (null !== $pendingRequest) {
            $isMultiOrgReauth = $this->shouldRouteToOrgaSelection($token, $pendingRequest);
            $hasBufferedRequest = null !== $pendingRequest->getRequestUrl();
            $hasPendingPageUrl = null !== $pendingRequest->getPageUrl();

            if ($isMultiOrgReauth && ($hasBufferedRequest || $hasPendingPageUrl)) {
                $this->cachePendingRequest($userId, $pendingRequest);
            }

            // Not yet implemented: single-org with buffered POST — cache and redirect to pending request review page
        }

        if (null !== $accessToken) {
            if (!$this->ozgKeycloakSessionManager->isKeycloakLoginOnly()) {
                $this->storeTokens($userId, $accessToken);
            }

            if ($this->ozgKeycloakSessionManager->isKeycloakLoginOnly()) {
                $this->handleLoginOnlyMode($request, $userId, $accessToken);
            }
        }

        return $this->resolvePostAuthRedirect($token, $isMultiOrgReauth, $pendingRequest);
    }

    /**
     * Determine the redirect response after authentication.
     *
     * Three cases: multi-org reauth → org selection, single-org reauth → pending page, fresh login → home.
     */
    private function resolvePostAuthRedirect(
        TokenInterface $token,
        bool $isMultiOrgReauth,
        ?PendingRequestData $pendingRequest,
    ): Response {
        if ($isMultiOrgReauth) {
            $this->messageBag->add('confirm', 'confirm.session.renewed');

            return new RedirectResponse($this->router->generate('DemosPlan_user_select_organisation'));
        }

        if (null !== $pendingRequest?->getPageUrl()) {
            return $this->handleSingleOrgaReauth($token, $pendingRequest);
        }

        return $this->handleFreshLogin($token);
    }

    /**
     * Read pending request data from the OAuthToken entity.
     *
     * Body stays encrypted (decryptBody=false) so it can be passed straight to cache.
     *
     * @throws CryptoException
     */
    private function readPendingRequest(string $userId): ?PendingRequestData
    {
        return $this->oauthTokenStorageService->getPendingRequest($userId);
    }

    /**
     * Determine if the user should be routed through the org-selection page.
     *
     * True when the user belongs to multiple organisations AND the pending request
     * has a stored organisation context (so the controller can compare).
     */
    private function shouldRouteToOrgaSelection(TokenInterface $token, PendingRequestData $pendingRequest): bool
    {
        $user = $token->getUser();

        return $user instanceof User
            && $this->currentOrganisationService->hasMultipleOrganisations($user)
            && null !== $pendingRequest->getSelectedOrganisationId();
    }

    /**
     * Cache pending request data for retrieval by the org-selection controller.
     */
    private function cachePendingRequest(string $userId, PendingRequestData $pendingRequest): void
    {
        $this->pendingRequestCacheService->store($userId, $pendingRequest);
    }

    /**
     * Store OAuth tokens in the database entity and sync session threshold.
     *
     * Clears any pending request data from the entity (tokens-only state restored).
     *
     * @throws TokenStorageException
     */
    private function storeTokens(string $userId, AccessToken $accessToken): void
    {
        $this->oauthTokenStorageService->storeTokens($userId, $accessToken);
    }

    /**
     * Login-only mode: store id_token in session for logout and set session expiration.
     *
     * In this mode token refresh never runs, so these values are set once at login.
     */
    private function handleLoginOnlyMode(Request $request, string $userId, AccessToken $accessToken): void
    {
        $rawIdToken = $accessToken->getValues()['id_token'] ?? null;
        if (null !== $rawIdToken) {
            $this->ozgKeycloakSessionManager->storeIdTokenForLogout($request->getSession(), $rawIdToken);
        }

        $this->ozgKeycloakSessionManager->injectTokenExpirationIntoSession($request->getSession(), $userId);
    }

    /**
     * Single-org reauth: auto-select the org, then redirect to pending page or replay.
     */
    private function handleSingleOrgaReauth(TokenInterface $token, PendingRequestData $pendingRequest): Response
    {
        $user = $token->getUser();

        if ($user instanceof User && 1 === $user->getOrganisations()->count()) {
            $singleOrga = $user->getOrganisations()->first();
            if (false !== $singleOrga) {
                $this->currentOrganisationService->setCurrentOrganisation($user, $singleOrga);
            }
        }

        $this->messageBag->add('confirm', 'confirm.session.renewed');

        // Not yet implemented: if pendingRequest has a buffered POST, redirect to pending request review page

        return new RedirectResponse($pendingRequest->getPageUrl());
    }

    /**
     * Fresh login: multi-org selection page or single-org auto-select + home redirect.
     */
    private function handleFreshLogin(TokenInterface $token): Response
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

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Keycloak login failed', ['exception' => $exception]);

        return new RedirectResponse($this->router->generate('core_login_idp_error'));
    }
}
