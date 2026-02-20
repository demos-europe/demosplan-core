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
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Exception\TokenEncryptionException;
use demosplan\DemosPlanCoreBundle\Exception\TokenStorageException;
use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakSessionManager;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\PendingRequestData;
use demosplan\DemosPlanCoreBundle\ValueObject\TokenData;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

/**
 * High-level service for OAuth token storage with automatic encryption/decryption.
 *
 * This service provides a clean API for managing OAuth tokens and pending requests,
 * handling encryption/decryption transparently.
 */
class OAuthTokenStorageService
{
    private const TIMEZONE = 'Europe/Berlin';

    public function __construct(
        private readonly OAuthTokenRepository $oauthTokenRepository,
        private readonly UserRepository $userRepository,
        private readonly TokenEncryptionService $encryptionService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
        private readonly RequestStack $requestStack,
        private readonly OzgKeycloakSessionManager $ozgKeycloakSessionManager,
    ) {
    }

    /**
     * Store or update OAuth tokens for a user.
     *
     * @param string      $userId      The user ID (UUID)
     * @param AccessToken $accessToken The AccessToken object from OAuth2 client
     *
     * @throws TokenStorageException if token storage fails
     */
    public function storeTokens(string $userId, AccessToken $accessToken): void
    {
        try {
            $user = $this->userRepository->get($userId);
            $oauthToken = $this->oauthTokenRepository->findByUserId($userId);

            if (null === $oauthToken) {
                $oauthToken = new OAuthToken();
                $oauthToken->setUser($user);
                $this->entityManager->persist($oauthToken);
            }

            // Extract token data
            $accessTokenString = $accessToken->getToken();
            $refreshTokenString = $accessToken->getRefreshToken();
            $expiresTimestamp = $accessToken->getExpires();
            /** @var array{refresh_expires_in?: int, token_type?: string, id_token?: string, 'not-before-policy'?: int, session_state?: string, scope?: string} $values */
            $values = $accessToken->getValues();

            // Validate required token fields
            Assert::stringNotEmpty($accessTokenString, 'Access token cannot be empty');
            Assert::positiveInteger($expiresTimestamp, 'Access token expiration timestamp must be a positive integer');
            Assert::keyExists($values, 'id_token', 'ID token is required for Keycloak logout');
            Assert::stringNotEmpty($values['id_token'], 'ID token cannot be empty');

            // Validate optional fields when present
            if (null !== $refreshTokenString) {
                Assert::stringNotEmpty($refreshTokenString, 'Refresh token cannot be empty when present');
            }

            if (isset($values['refresh_expires_in'])) {
                Assert::positiveInteger($values['refresh_expires_in'], 'Refresh token expiration must be a positive integer when present');
            }

            // Encrypt and store access token
            $encryptedAccessToken = $this->encryptionService->encrypt($accessTokenString);
            $oauthToken->setAccessToken($encryptedAccessToken);

            // Encrypt and store refresh token
            if (null !== $refreshTokenString) {
                $encryptedRefreshToken = $this->encryptionService->encrypt($refreshTokenString);
                $oauthToken->setRefreshToken($encryptedRefreshToken);
            }

            // Encrypt and store ID token
            if (isset($values['id_token'])) {
                $encryptedIdToken = $this->encryptionService->encrypt($values['id_token']);
                $oauthToken->setIdToken($encryptedIdToken);
            }

            // Calculate and store expiration times
            $timezone = new DateTimeZone(self::TIMEZONE);
            $accessTokenExpiresAt = null;

            if (null !== $expiresTimestamp) {
                $accessTokenExpiresAt = new DateTime('now', $timezone);
                $accessTokenExpiresAt->setTimestamp($expiresTimestamp);
                $oauthToken->setAccessTokenExpiresAt($accessTokenExpiresAt);
            }

            // Calculate refresh token expiration from refresh_expires_in
            if (isset($values['refresh_expires_in'])) {
                $refreshExpiresIn = (int) $values['refresh_expires_in'];
                $refreshTokenExpiresAt = new DateTime('now', $timezone);
                $refreshTokenExpiresAt->modify("+{$refreshExpiresIn} seconds");
                $oauthToken->setRefreshTokenExpiresAt($refreshTokenExpiresAt);
            }

            // Clear any buffered request after successful token storage
            // If tokens were expired and a request was buffered (entity in "buffered request" state),
            // we now have fresh tokens (entity transitioning back to "active tokens" state).
            // The buffered request should be cleared as it will be/was replayed after re-authentication.
            if ($oauthToken->hasPendingRequest()) {
                $oauthToken->clearPendingRequest();
            }

            $this->entityManager->flush();

            $this->logger->info('OAuth tokens stored', [
                'user_id' => $userId,
                'has_refresh_token' => null !== $refreshTokenString,
                'has_id_token' => isset($values['id_token']),
            ]);

            // Sync session threshold and expiration after every token store.
            // Uses the expiry already computed above — no extra DB query needed.
            $request = $this->requestStack->getCurrentRequest();
            if (null !== $request) {
                $session = $request->getSession();
                if ($session->isStarted()) {
                    $this->ozgKeycloakSessionManager->syncSession($session, $userId, $accessTokenExpiresAt);
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to store OAuth tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new TokenStorageException('Failed to store OAuth tokens: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get decrypted OAuth tokens for a user.
     *
     * @param string $userId The user ID (UUID)
     *
     * @return TokenData|null Token data value object with decrypted tokens, or null if no tokens found
     *
     * @throws TokenEncryptionException if decryption fails
     */
    public function getClearTokenData(string $userId): ?TokenData
    {
        $oauthToken = $this->oauthTokenRepository->findByUserId($userId);

        // return early if no valid tokens exist - request buffer might still be present
        if (null === $oauthToken || null === $oauthToken->getAccessToken()) {
            return null;
        }

        $tokenData = new TokenData();
        $tokenData->fill($oauthToken, $this->encryptionService);

        return $tokenData;
    }

    /**
     * Delete OAuth tokens for a user.
     *
     * @param string $userId The user ID (UUID)
     */
    public function deleteTokens(string $userId): void
    {
        $this->oauthTokenRepository->deleteByUserId($userId);

        $this->logger->info('OAuth tokens deleted', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Delete OAuth tokens only if no pending request is buffered.
     *
     * When a request was buffered before logout (e.g. a POST that triggered token expiry),
     * we must keep the token entity alive so the user can retrieve it after re-authentication.
     * The maintenance command will clean up abandoned buffered tokens after the configured timeout.
     *
     * @param string $userId The user ID (UUID)
     *
     * @return bool true if deleted, false if skipped due to pending request
     */
    public function deleteTokensUnlessPendingRequest(string $userId): bool
    {
        $oauthToken = $this->oauthTokenRepository->findByUserId($userId);

        if (null === $oauthToken) {
            return true;
        }

        if ($oauthToken->hasPendingRequest()) {
            $this->logger->info('OAuth token deletion skipped - pending request buffer preserved for re-authentication', [
                'user_id' => $userId,
                'pending_request_url' => $oauthToken->getPendingRequestUrl(),
                'pending_request_timestamp' => $oauthToken->getPendingRequestTimestamp()?->format('Y-m-d H:i:s'),
            ]);

            return false;
        }

        $this->oauthTokenRepository->deleteByUserId($userId);

        $this->logger->info('OAuth tokens deleted', [
            'user_id' => $userId,
        ]);

        return true;
    }

    /**
     * Store a pending request for replay after re-authentication.
     *
     * @param OAuthToken         $oauthToken  The OAuth token entity to store the request buffer in
     * @param PendingRequestData $requestData Request data value object with clear (unencrypted) data
     *
     * @throws TokenEncryptionException if encryption of request body fails
     * @throws \InvalidArgumentException if validation of request data fails
     */
    public function storePendingRequest(OAuthToken $oauthToken, PendingRequestData $requestData): void
    {
        // Clear expired tokens when buffering request
        // Entity transitions from "active tokens" state to "buffered request" state.
        // Expired/invalid tokens are removed, and the request is buffered until re-authentication.
        $oauthToken->clearTokens();

        $oauthToken->setPendingPageUrl($requestData->getPageUrl());
        $oauthToken->setPendingRequestUrl($requestData->getRequestUrl());
        $oauthToken->setPendingRequestMethod($requestData->getMethod());
        $oauthToken->setPendingRequestContentType($requestData->getContentType());
        $oauthToken->setPendingRequestHasFiles($requestData->getHasFiles());
        $oauthToken->setPendingRequestFilesMetadata($requestData->getFilesMetadata());
        $oauthToken->setPendingRequestTimestamp($requestData->getTimestamp());

        // Encrypt request body before storing (encryption happens in service layer)
        if (null !== $requestData->getBody() && '' !== $requestData->getBody()) {
            $encrypted = $this->encryptionService->encrypt($requestData->getBody());
            $oauthToken->setPendingRequestBody($encrypted);
        }

        // Validate entity constraints
        $violations = $this->validator->validate($oauthToken);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath().': '.$violation->getMessage();
            }
            throw new \InvalidArgumentException('Validation failed: '.implode(', ', $errors));
        }

        $this->entityManager->flush();

        $this->logger->info('Pending request stored', [
            'user_id' => $oauthToken->getUser()->getId(),
            'request_url' => $requestData->getRequestUrl(),
            'method' => $requestData->getMethod(),
        ]);
    }

    /**
     * Get pending request for a user.
     *
     * @param string $userId The user ID (UUID)
     *
     * @return PendingRequestData|null Request data value object with decrypted body, or null if no pending request
     *
     * @throws TokenEncryptionException if decryption of request body fails
     */
    public function getPendingRequest(string $userId): ?PendingRequestData
    {
        $oauthToken = $this->oauthTokenRepository->findByUserId($userId);

        if (null === $oauthToken || !$oauthToken->hasPendingRequest()) {
            return null;
        }

        // Decrypt request body before filling (encryption happens in service layer)
        $clearBody = null;
        if (null !== $oauthToken->getPendingRequestBody()) {
            $clearBody = $this->encryptionService->decrypt($oauthToken->getPendingRequestBody());
        }

        $requestData = new PendingRequestData();
        $requestData->fill([
            'pageUrl' => $oauthToken->getPendingPageUrl(),
            'requestUrl' => $oauthToken->getPendingRequestUrl(),
            'method' => $oauthToken->getPendingRequestMethod(),
            'contentType' => $oauthToken->getPendingRequestContentType(),
            'hasFiles' => $oauthToken->hasPendingRequestFiles(),
            'filesMetadata' => $oauthToken->getPendingRequestFilesMetadata(),
            'timestamp' => $oauthToken->getPendingRequestTimestamp(),
            'body' => $clearBody,
        ]);

        return $requestData;
    }
}
