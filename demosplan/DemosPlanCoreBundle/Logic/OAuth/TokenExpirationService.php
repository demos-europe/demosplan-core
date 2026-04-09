<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OAuth;

use DateTime;
use DateTimeZone;
use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Controller\GenericRpcController;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Service for OAuth token expiration checks and expired-token response handling.
 *
 * Expiration check methods accept OAuthToken entities directly for maximum performance —
 * callers can fetch the entity once and perform multiple checks.
 * handleExpiredTokens() orchestrates the full response when tokens are expired:
 * buffering the request, storing the id_token for logout, and redirecting or returning
 * a 401 depending on the controller type.
 */
class TokenExpirationService
{
    private readonly DateTimeZone $tokenTimezone;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly OAuthTokenStorageService $oauthTokenStorageService,
        private readonly OrgaRepository $orgaRepository,
        private readonly OzgKeycloakSessionManager $ozgKeycloakSessionManager,
        private readonly RouterInterface $router,
        private readonly SecretEncryptor $tokenEncryptionService,
        #[Autowire('%oauth_token_timezone%')]
        string $tokenTimezone,
    ) {
        $this->tokenTimezone = new DateTimeZone($tokenTimezone);
    }

    /**
     * Store id_token in session, buffer the current request, and redirect to logout.
     *
     * Accepts a nullable OAuthToken — when null (no stored credentials found), buffering
     * and id_token storage are skipped but the API vs redirect decision is still enforced.
     */
    public function handleExpiredTokens(ControllerEvent $event, ?OAuthToken $oauthToken): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        if (null !== $oauthToken) {
            try {
                $encryptedIdToken = $oauthToken->getIdToken();
                if (null !== $encryptedIdToken) {
                    $this->ozgKeycloakSessionManager->storeIdTokenForLogout(
                        $session,
                        $this->tokenEncryptionService->decrypt($encryptedIdToken)
                    );
                }
            } catch (Exception $e) {
                $this->logger->error('Failed to store id_token for logout', ['error' => $e->getMessage()]);
            }

            $this->oauthTokenStorageService->bufferRequestIfNeeded($oauthToken);

            // Store page URL and selected org ID for redirect-back after re-authentication.
            // For POST requests this is a cheap redundant write — bufferRequestIfNeeded already set it.
            // For GET requests this is the only write.
            // Skip logout and connect routes: redirecting back to those would re-trigger logout or an auth loop.
            $path = $request->getPathInfo();
            $logoutPath = $this->router->generate('DemosPlan_user_logout');
            $connectPath = $this->router->generate('connect_keycloak_ozg');
            if (!str_contains($path, $logoutPath) && !str_contains($path, $connectPath)) {
                try {
                    $orgId = $session->get(CurrentOrganisationService::SESSION_KEY);
                    if (null !== $orgId) {
                        $orga = $this->orgaRepository->find($orgId);
                        if ($orga instanceof Orga) {
                            $oauthToken->setSelectedOrganisation($orga);
                        }
                    }
                    $this->oauthTokenStorageService->storePendingPageUrl($oauthToken, $path);
                } catch (Exception $e) {
                    $this->logger->error('Failed to store pending page URL for redirect-back', ['error' => $e->getMessage()]);
                }
            }
        }

        $controller = $this->resolveController($event);

        if ($controller instanceof APIController) {
            $response = $controller->handleApiError(new AccessDeniedException('Token expired'));
            $event->setController(fn () => $response);

            return;
        }

        if ($controller instanceof GenericRpcController) {
            $response = new JsonResponse(['error' => 'Token expired'], Response::HTTP_UNAUTHORIZED);
            $event->setController(fn () => $response);

            return;
        }

        $this->redirectToLogout($event);
    }

    private function resolveController(ControllerEvent $event): ?object
    {
        $callable = $event->getController();
        $target = is_array($callable) ?
            $callable[0] ?? null
            : $callable;

        return is_object($target) ? $target : null;
    }

    private function redirectToLogout(ControllerEvent $event): void
    {
        $this->logger->info('Token expired, redirecting to logout');

        $redirectResponse = new RedirectResponse($this->router->generate('DemosPlan_user_logout'));
        $event->setController(static fn () => $redirectResponse);
    }

    /**
     * Check if the access token is currently expired.
     *
     * CONTEXT: Blocking requests (kernel.controller), non-blocking requests (kernel.terminate)
     * USAGE: Check actual expiration without buffer time.
     *
     * @param OAuthToken|null $token The OAuth token entity (null if no token exists)
     *
     * @return bool True if access token is expired or missing, false if still valid
     */
    public function isAccessTokenExpired(?OAuthToken $token): bool
    {
        // No token or no expiration timestamp → expired
        if (null === $token || null === $token->getAccessTokenExpiresAt()) {
            return true;
        }

        $timezone = $this->tokenTimezone;
        $now = new DateTime('now', $timezone);

        // Check actual expiration (no buffer)
        return $now >= $token->getAccessTokenExpiresAt();
    }

    /**
     * Check if the refresh token is currently expired.
     *
     * CONTEXT: Blocking requests (kernel.controller), non-blocking requests (kernel.terminate)
     * USAGE: Determines if token refresh is possible or if user must re-authenticate.
     *
     * @param OAuthToken|null $token The OAuth token entity (null if no token exists)
     *
     * @return bool True if refresh token is expired/missing, false if still valid
     */
    public function isRefreshTokenExpired(?OAuthToken $token): bool
    {
        // No token or no refresh token → expired
        if (null === $token || null === $token->getRefreshToken()) {
            return true;
        }

        // No expiration timestamp → invalid state, consider expired
        if (null === $token->getRefreshTokenExpiresAt()) {
            return true;
        }

        $timezone = $this->tokenTimezone;
        $now = new DateTime('now', $timezone);

        // Check actual expiration (no buffer)
        return $now >= $token->getRefreshTokenExpiresAt();
    }

    /**
     * Check if the access token needs to be refreshed proactively.
     *
     * CONTEXT: Non-blocking requests (kernel.terminate)
     * USAGE: Background token refresh before expiration occurs.
     *
     * Returns true if:
     * - No token exists (needs authentication)
     * - Token will expire within buffer period (needs proactive refresh)
     * - Token has no expiration (invalid state)
     *
     * Returns false only if token is valid with > bufferMinutes remaining.
     *
     * @param OAuthToken|null $token         The OAuth token entity (null if no token exists)
     * @param int             $bufferMinutes Buffer time in minutes before expiration (default: 2)
     *
     * @return bool True if refresh is needed, false if token is still valid with sufficient time
     */
    public function accessTokenNeedsRefresh(?OAuthToken $token, int $bufferMinutes = 2): bool
    {
        // No token → needs refresh/authentication
        if (null === $token) {
            return true;
        }

        // No expiration timestamp → invalid state, needs refresh
        if (null === $token->getAccessTokenExpiresAt()) {
            return true;
        }

        $timezone = $this->tokenTimezone;
        $now = new DateTime('now', $timezone);
        $threshold = (clone $token->getAccessTokenExpiresAt())->modify("-{$bufferMinutes} minutes");

        // Token expires within buffer period → needs refresh
        return $now >= $threshold;
    }
}
