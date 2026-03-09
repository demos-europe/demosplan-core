<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DateTime;
use DateTimeZone;
use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\KeycloakTokenRefreshService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenEncryptionService;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenExpirationService;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\UserFromSecurityUserProvider;
use demosplan\DemosPlanCoreBundle\ValueObject\PendingRequestData;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Blocking request listener that checks OAuth token expiration and refreshes tokens if needed.
 *
 * This listener runs on every kernel.controller event (before request processing) to ensure
 * the user has valid OAuth tokens. Uses a session-based threshold to avoid checking on every
 * request - only performs database queries when the threshold has passed.
 *
 * Performance optimization: Stores "next_token_check" timestamp in session. If current time
 * is before this threshold, all token checks are skipped (fast path). Only when threshold
 * is reached, the listener queries database and performs token validation/refresh.
 * The fast-path interval is between 0 and the configured maximum (default 3 minutes), capped
 * at the token's remaining lifetime so we never skip a check past the actual token expiry.
 *
 * Flow:
 * 1. Check session threshold - return early if not reached (fast path, no DB queries)
 * 2. Query database for OAuthToken
 * 3. Check if access token is expired (TokenExpirationService)
 * 4. If expired and refresh token valid: refresh tokens (KeycloakTokenRefreshService)
 * 5. If refresh token also expired: buffer the current request for user review after
 *    re-authentication (automatic replay is unsafe due to JS context loss), store id_token
 *    in session for KeyCloak logout URL, then redirect to logout
 * 6. Update session threshold for next check (0 to configured max, capped at token lifetime)
 *
 * CONTEXT: Blocking requests (kernel.controller)
 * USAGE: Ensures valid tokens before request processing
 */
#[AsEventListener(event: 'kernel.controller', priority: 5)]
class ExpirationTimestampRequestListener
{
    private const TIMEZONE = 'Europe/Berlin';

    public function __construct(
        private readonly Security $security,
        private readonly UserFromSecurityUserProvider $userFromSecurityUserProvider,
        private readonly OAuthTokenRepository $oauthTokenRepository,
        private readonly OAuthTokenStorageService $oauthTokenStorageService,
        private readonly TokenEncryptionService $tokenEncryptionService,
        private readonly TokenExpirationService $tokenExpirationService,
        private readonly KeycloakTokenRefreshService $tokenRefreshService,
        private readonly OzgKeycloakSessionManager $ozgKeycloakSessionManager,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if ($this->shallReturnEarlyAndCheap($event)) {
            return;
        }

        if ($this->shallReturnDueToUserConfig()) {
            return;
        }

        // User and IdP checks are guaranteed by shallReturnDueToUserConfig — safe to load token here.
        // The provider caches its result, so this get() costs no additional DB query.
        $user = $this->userFromSecurityUserProvider->get();
        $oauthToken = $this->oauthTokenRepository->findByUserId($user->getId());

        // null token means no stored credentials — treat as expired and force re-authentication.
        // hasValidTokens is only called when a token exists and takes a non-nullable OAuthToken.
        if (null !== $oauthToken && $this->hasValidTokens($event, $oauthToken)) {
            return;
        }

        $this->handleExpiredTokens($event, $oauthToken);
    }

    private function shallReturnDueToUserConfig(): bool
    {
        // TODO: TECHNICAL DEBT - The hasLogoutWarningPermission() check is problematic and needs investigation.
        // The permission 'feature_auto_logout_warning' is described as "Remind the user of the upcoming automatic log out"
        // (a UI notification feature), but the actual code performs HARD LOGOUT with no warnings.
        // Without this permission, KeyCloak is only used for initial login and subsequent requests
        // validate against the PHP session only. It's unclear if this is intentional or unfinished.
        // Currently keeping this check for backward compatibility - all diplan (KeyCloak) projects have it enabled.
        if (!$this->ozgKeycloakSessionManager->hasLogoutWarningPermission()) {
            return true;
        }

        // Load the application User entity (one DB query, result cached on the provider).
        // Null means the security user has no matching User in the database (e.g. edge cases).
        // Non-IdP users (form-login) are also skipped — token management only applies to KeyCloak users.
        $user = $this->userFromSecurityUserProvider->get();

        return null === $user || !$user->isProvidedByIdentityProvider();
    }

    /**
     * Cheap early-return checks that run on every single request with zero DB queries.
     *
     * Checks (in order of cost, cheapest first):
     * - Session not started
     * - KeyCloak not configured in production
     * - Not a main request (sub-requests skip)
     * - User is a non-human (FunctionalUser — AnonymousUser, AiApiUser, etc.)
     * - Session threshold not yet reached (tokens were recently validated)
     */
    private function shallReturnEarlyAndCheap(ControllerEvent $event): bool
    {
        $session = $event->getRequest()->getSession();

        if (!$session->isStarted()
            || $this->ozgKeycloakSessionManager->shouldSkipInProductionWithoutKeycloak()
            || !$event->isMainRequest()
            || $this->security->getUser() instanceof FunctionalUser) {
            return true;
        }

        // Session threshold check - session is user-specific, no user ID needed here
        $nextTokenCheck = $session->get(OzgKeycloakSessionManager::NEXT_TOKEN_CHECK);
        $currentTime = time();
        $thresholdNotReached = null !== $nextTokenCheck && $currentTime < $nextTokenCheck;

        if ($thresholdNotReached) {
            $this->logger->debug('Token check skipped - threshold not reached', [
                'current_time'        => date('Y-m-d H:i:s', $currentTime),
                'next_check'          => date('Y-m-d H:i:s', $nextTokenCheck),
                'seconds_until_check' => $nextTokenCheck - $currentTime,
            ]);
        }

        return $thresholdNotReached;
    }

    /**
     * Checks whether the user has valid OAuth tokens, refreshing if needed. Costs at least one DB query.
     *
     * Returns true if access token is valid or was successfully refreshed.
     * Returns false if both tokens are expired or refresh failed.
     */
    private function hasValidTokens(ControllerEvent $event, OAuthToken $oauthToken): bool
    {
        $session = $event->getRequest()->getSession();

        $this->logger->debug('Token check threshold reached - performing validation', [
            'user_id'      => $oauthToken->getUser()->getId(),
            'current_time' => date('Y-m-d H:i:s', time()),
        ]);

        if (!$this->tokenExpirationService->isAccessTokenExpired($oauthToken)) {
            $this->ozgKeycloakSessionManager->syncSession($session, $oauthToken->getUser()->getId(), $oauthToken->getAccessTokenExpiresAt());

            return true;
        }

        return $this->tryRefreshToken($oauthToken);
    }

    /**
     * Attempts to refresh the access token using the refresh token.
     *
     * Returns true if refresh was successful, false if refresh token is also expired or refresh failed.
     */
    private function tryRefreshToken(OAuthToken $oauthToken): bool
    {
        $userId = $oauthToken->getUser()->getId();

        $this->logger->info('Access token expired - checking refresh token', ['user_id' => $userId]);

        if ($this->tokenExpirationService->isRefreshTokenExpired($oauthToken)) {
            $this->logger->info('Refresh token also expired', ['user_id' => $userId]);

            return false;
        }

        $refreshSuccess = $this->tokenRefreshService->refreshTokensForUser($userId);

        if (!$refreshSuccess) {
            $this->logger->error('Token refresh failed', ['user_id' => $userId]);

            return false;
        }

        $this->logger->info('Token refresh successful - continuing request', ['user_id' => $userId]);

        return true;
    }

    /**
     * Store id_token in session, buffer the current request, and redirect to logout.
     *
     * Accepts a nullable OAuthToken — when null (no stored credentials found), buffering
     * and id_token storage are skipped but the API vs redirect decision is still enforced.
     */
    private function handleExpiredTokens(ControllerEvent $event, ?OAuthToken $oauthToken): void
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

            if ($this->shouldBufferRequest($request)) {
                try {
                    $this->bufferRequest($request, $oauthToken);
                } catch (Exception $e) {
                    $this->logger->error('Failed to buffer request', ['error' => $e->getMessage()]);
                }
            }

            // Store page URL for redirect-back after re-authentication.
            // For POST requests this is a cheap redundant write — bufferRequest already set it.
            // For GET requests this is the only write.
            try {
                $this->oauthTokenStorageService->storePendingPageUrl($oauthToken, $request->getPathInfo());
            } catch (Exception $e) {
                $this->logger->error('Failed to store pending page URL for redirect-back', ['error' => $e->getMessage()]);
            }
        }

        $controller = $this->resolveController($event);
        if ($controller instanceof APIController) {
            $response = $controller->handleApiError(new AccessDeniedException('Token expired'));
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

    /**
     * Check if the request should be buffered.
     *
     * Only buffer requests that are worth a review (POST, PUT, PATCH, DELETE with data).
     */
    private function shouldBufferRequest(Request $request): bool
    {
        $method = $request->getMethod();

        // Don't buffer GET, HEAD, OPTIONS requests
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        // Don't buffer if already on logout page
        if (str_contains($request->getPathInfo(), '/logout')) {
            return false;
        }

        return true;
    }

    /**
     * Buffer the current request for replay after re-authentication.
     */
    private function bufferRequest(Request $request, OAuthToken $oauthToken): void
    {
        try {
            $timezone = new DateTimeZone(self::TIMEZONE);
            $requestData = new PendingRequestData();

            // Build request data value object
            $requestData->fill([
                'pageUrl'       => $request->getPathInfo(),
                'requestUrl'    => $request->getRequestUri(),
                'method'        => $request->getMethod(),
                'contentType'   => $request->headers->get('Content-Type'),
                'hasFiles'      => $request->files->count() > 0,
                'filesMetadata' => $this->getFilesMetadata($request),
                'timestamp'     => new DateTime('now', $timezone),
                'body'          => $this->getRequestBody($request),
            ]);

            $this->oauthTokenStorageService->storePendingRequest($oauthToken, $requestData);

            $this->logger->info('Request buffered for replay after re-authentication', [
                'method' => $request->getMethod(),
                'url'    => $request->getRequestUri(),
            ]);
        } catch (Exception $e) {
            $this->logger->error(
                'Failed to buffer last request before logout',
                ['exceptionMessage' => $e->getMessage(), 'exception' => get_class($e)]
            );
        }
    }

    /**
     * Get request body as string.
     */
    private function getRequestBody(Request $request): ?string
    {
        $content = $request->getContent();

        return '' !== $content ? $content : null;
    }

    /**
     * Get metadata about uploaded files.
     */
    private function getFilesMetadata(Request $request): ?array
    {
        if (0 === $request->files->count()) {
            return null;
        }

        $metadata = [];
        foreach ($request->files->all() as $key => $file) {
            if (is_array($file)) {
                /** @var UploadedFile $subFile */
                foreach ($file as $subKey => $subFile) {
                    $metadata[$key][$subKey] = [
                        'name' => $subFile->getClientOriginalName(),
                        'size' => $subFile->getSize(),
                        'type' => $subFile->getClientMimeType(),
                    ];
                }
            } else {
                /* @var UploadedFile $file */
                $metadata[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $file->getClientMimeType(),
                ];
            }
        }

        return $metadata;
    }

    /**
     * Redirect to logout route.
     *
     * The logout route will handle KeyCloak redirect if configured, or standard logout otherwise.
     * This maintains compatibility with both KeyCloak and non-KeyCloak projects.
     */
    private function redirectToLogout(ControllerEvent $event): void
    {
        $this->logger->info('Token expired, redirecting to logout');

        $redirectResponse = new RedirectResponse($this->router->generate('DemosPlan_user_logout'));
        $event->setController(static fn () => $redirectResponse);
    }
}
