<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Exception\TokenEncryptionException;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenEncryptionService;

/**
 * Value object for OAuth token data.
 *
 * @method string        getAccessToken()
 * @method string        getIdToken()
 * @method DateTime      getAccessTokenExpiresAt()
 * @method string|null   getRefreshToken()
 * @method DateTime|null getRefreshTokenExpiresAt()
 * @method string        getProvider()
 */
class TokenData extends ValueObject
{
    /**
     * The decrypted OAuth access token.
     */
    protected string $accessToken;

    /**
     * The decrypted OpenID Connect ID token.
     */
    protected string $idToken;

    /**
     * Access token expiration timestamp.
     */
    protected DateTime $accessTokenExpiresAt;

    /**
     * The decrypted OAuth refresh token (optional).
     */
    protected ?string $refreshToken = null;

    /**
     * Refresh token expiration timestamp (optional).
     */
    protected ?DateTime $refreshTokenExpiresAt = null;

    /**
     * OAuth provider name (e.g., 'keycloak_ozg').
     */
    protected string $provider;

    /**
     * Fill the value object with decrypted token data from an OAuthToken entity.
     *
     * @throws TokenEncryptionException if decryption fails
     */
    public function fill(OAuthToken $oauthToken, TokenEncryptionService $encryptionService): void
    {
        // Decrypt required tokens (guaranteed by validation on storage)
        $this->accessToken = $encryptionService->decrypt($oauthToken->getAccessToken());
        $this->idToken = $encryptionService->decrypt($oauthToken->getIdToken());
        $this->accessTokenExpiresAt = $oauthToken->getAccessTokenExpiresAt();
        $this->provider = $oauthToken->getProvider();

        // Decrypt optional tokens
        if (null !== $oauthToken->getRefreshToken()) {
            $this->refreshToken = $encryptionService->decrypt($oauthToken->getRefreshToken());
        }

        if (null !== $oauthToken->getRefreshTokenExpiresAt()) {
            $this->refreshTokenExpiresAt = $oauthToken->getRefreshTokenExpiresAt();
        }

        $this->lock();
    }
}
