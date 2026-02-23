<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores OAuth tokens for KeyCloak authentication and pending requests during token refresh.
 *
 * @ORM\Table(
 *     name="oauth_tokens",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="unique_user_id", columns={"user_id"})},
 *     indexes={
 *         @ORM\Index(name="idx_access_expires", columns={"access_token_expires_at"}),
 *         @ORM\Index(name="idx_pending_timestamp", columns={"pending_request_timestamp"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository")
 */
class OAuthToken
{
    private const TIMEZONE = 'Europe/Berlin';

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private ?int $id = null;

    /**
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="_u_id", nullable=false, onDelete="CASCADE")
     */
    private User $user;

    // ===== OAUTH TOKENS (When authenticated) =====

    /**
     * @ORM\Column(name="access_token", type="text", nullable=true)
     */
    private ?string $accessToken = null;

    /**
     * @ORM\Column(name="refresh_token", type="text", nullable=true)
     */
    private ?string $refreshToken = null;

    /**
     * @ORM\Column(name="id_token", type="text", nullable=true)
     */
    private ?string $idToken = null;

    /**
     * @ORM\Column(name="access_token_expires_at", type="datetime", nullable=true)
     */
    private ?DateTime $accessTokenExpiresAt = null;

    /**
     * @ORM\Column(name="refresh_token_expires_at", type="datetime", nullable=true)
     */
    private ?DateTime $refreshTokenExpiresAt = null;

    // ===== PENDING REQUEST (When awaiting re-auth) =====

    /**
     * @ORM\Column(name="pending_page_url", type="text", nullable=true)
     */
    #[Assert\Regex(pattern: '/^\//', message: 'Pending page URL must be an internal path starting with /')]
    private ?string $pendingPageUrl = null;

    /**
     * @ORM\Column(name="pending_request_url", type="text", nullable=true)
     */
    #[Assert\Regex(pattern: '/^\//', message: 'Pending request URL must be an internal path starting with /')]
    private ?string $pendingRequestUrl = null;

    /**
     * @ORM\Column(name="pending_request_method", type="string", length=10, nullable=true)
     */
    #[Assert\Choice(choices: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], message: 'HTTP method must be one of: {{ choices }}')]
    private ?string $pendingRequestMethod = null;

    /**
     * @ORM\Column(name="pending_request_body", type="text", nullable=true)
     */
    private ?string $pendingRequestBody = null;

    /**
     * @ORM\Column(name="pending_request_content_type", type="string", length=100, nullable=true)
     */
    private ?string $pendingRequestContentType = null;

    /**
     * @ORM\Column(name="pending_request_has_files", type="boolean", options={"default": false})
     */
    private bool $pendingRequestHasFiles = false;

    /**
     * @ORM\Column(name="pending_request_files_metadata", type="json", nullable=true)
     */
    private ?array $pendingRequestFilesMetadata = null;

    /**
     * @ORM\Column(name="pending_request_timestamp", type="datetime", nullable=true)
     */
    private ?DateTime $pendingRequestTimestamp = null;

    // ===== PROVIDER & TIMESTAMPS =====

    /**
     * @ORM\Column(name="provider", type="string", length=50, nullable=false, options={"default": "keycloak_ozg"})
     */
    private string $provider = 'keycloak_ozg';

    /**
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     */
    private DateTime $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime", nullable=false)
     */
    private DateTime $updatedAt;

    public function __construct()
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $this->createdAt = new DateTime('now', $timezone);
        $this->updatedAt = new DateTime('now', $timezone);
    }

    // ===== GETTERS & SETTERS =====

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->updateTimestamp();
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
        $this->updateTimestamp();
    }

    public function getIdToken(): ?string
    {
        return $this->idToken;
    }

    public function setIdToken(?string $idToken): void
    {
        $this->idToken = $idToken;
        $this->updateTimestamp();
    }

    public function getAccessTokenExpiresAt(): ?DateTime
    {
        return $this->accessTokenExpiresAt;
    }

    public function setAccessTokenExpiresAt(?DateTime $accessTokenExpiresAt): void
    {
        $this->accessTokenExpiresAt = $accessTokenExpiresAt;
        $this->updateTimestamp();
    }

    public function getRefreshTokenExpiresAt(): ?DateTime
    {
        return $this->refreshTokenExpiresAt;
    }

    public function setRefreshTokenExpiresAt(?DateTime $refreshTokenExpiresAt): void
    {
        $this->refreshTokenExpiresAt = $refreshTokenExpiresAt;
        $this->updateTimestamp();
    }

    public function getPendingPageUrl(): ?string
    {
        return $this->pendingPageUrl;
    }

    public function setPendingPageUrl(?string $pendingPageUrl): void
    {
        $this->pendingPageUrl = $pendingPageUrl;
        $this->updateTimestamp();
    }

    public function getPendingRequestUrl(): ?string
    {
        return $this->pendingRequestUrl;
    }

    public function setPendingRequestUrl(?string $pendingRequestUrl): void
    {
        $this->pendingRequestUrl = $pendingRequestUrl;
        $this->updateTimestamp();
    }

    public function getPendingRequestMethod(): ?string
    {
        return $this->pendingRequestMethod;
    }

    public function setPendingRequestMethod(?string $pendingRequestMethod): void
    {
        $this->pendingRequestMethod = $pendingRequestMethod;
        $this->updateTimestamp();
    }

    public function getPendingRequestBody(): ?string
    {
        return $this->pendingRequestBody;
    }

    public function setPendingRequestBody(?string $pendingRequestBody): void
    {
        $this->pendingRequestBody = $pendingRequestBody;
        $this->updateTimestamp();
    }

    public function getPendingRequestContentType(): ?string
    {
        return $this->pendingRequestContentType;
    }

    public function setPendingRequestContentType(?string $pendingRequestContentType): void
    {
        $this->pendingRequestContentType = $pendingRequestContentType;
        $this->updateTimestamp();
    }

    public function hasPendingRequestFiles(): bool
    {
        return $this->pendingRequestHasFiles;
    }

    public function setPendingRequestHasFiles(bool $pendingRequestHasFiles): void
    {
        $this->pendingRequestHasFiles = $pendingRequestHasFiles;
        $this->updateTimestamp();
    }

    public function getPendingRequestFilesMetadata(): ?array
    {
        return $this->pendingRequestFilesMetadata;
    }

    public function setPendingRequestFilesMetadata(?array $pendingRequestFilesMetadata): void
    {
        $this->pendingRequestFilesMetadata = $pendingRequestFilesMetadata;
        $this->updateTimestamp();
    }

    public function getPendingRequestTimestamp(): ?DateTime
    {
        return $this->pendingRequestTimestamp;
    }

    public function setPendingRequestTimestamp(?DateTime $pendingRequestTimestamp): void
    {
        $this->pendingRequestTimestamp = $pendingRequestTimestamp;
        $this->updateTimestamp();
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
        $this->updateTimestamp();
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    // ===== HELPER METHODS =====

    /**
     * Check if there is a full request buffer (POST/PUT/etc.) pending replay.
     * Does NOT cover URL-only entries — use hasPendingData() for that.
     */
    public function hasPendingRequest(): bool
    {
        return null !== $this->pendingRequestUrl;
    }

    /**
     * Check if a redirect-back page URL has been stored.
     * Can be true even when hasPendingRequest() is false (URL-only entry from a GET).
     */
    public function hasPendingPageUrlSet(): bool
    {
        return null !== $this->pendingPageUrl;
    }

    /**
     * Check if there is any pending data (full request buffer OR page URL).
     * Use this for deletion guards, storeTokens checks, and clearOutdated queries.
     */
    public function hasPendingData(): bool
    {
        return $this->hasPendingRequest() || $this->hasPendingPageUrlSet();
    }

    /**
     * Clear all pending request data.
     */
    public function clearPendingRequest(): void
    {
        $this->pendingPageUrl = null;
        $this->pendingRequestUrl = null;
        $this->pendingRequestMethod = null;
        $this->pendingRequestBody = null;
        $this->pendingRequestContentType = null;
        $this->pendingRequestHasFiles = false;
        $this->pendingRequestFilesMetadata = null;
        $this->pendingRequestTimestamp = null;
        $this->updateTimestamp();
    }

    /**
     * Check if pending request is older than specified minutes.
     */
    public function isPendingRequestExpired(int $minutes): bool
    {
        if (null === $this->pendingRequestTimestamp) {
            return false;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $now = new DateTime('now', $timezone);
        $threshold = (clone $this->pendingRequestTimestamp)->modify("+{$minutes} minutes");

        return $now >= $threshold;
    }

    /**
     * Clear all OAuth tokens.
     */
    public function clearTokens(): void
    {
        $this->accessToken = null;
        $this->refreshToken = null;
        $this->idToken = null;
        $this->accessTokenExpiresAt = null;
        $this->refreshTokenExpiresAt = null;
        $this->updateTimestamp();
    }

    private function updateTimestamp(): void
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $this->updatedAt = new DateTime('now', $timezone);
    }
}
