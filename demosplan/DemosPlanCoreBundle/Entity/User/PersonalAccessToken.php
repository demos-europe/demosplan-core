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
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Long-lived, scoped, revocable API credential owned by a single user and bound to a single customer.
 *
 * The full token presented by the client has the shape `dplan_pat_<prefix><secret>` where
 * - prefix is {@see PersonalAccessToken::TOKEN_PREFIX_LENGTH} chars, stored plaintext for lookup
 * - secret is hashed with the configured password hasher and never persisted in the clear
 *
 * A PAT can never grant a permission the owning user does not already possess; the scope list
 * is intersected with the user's effective permissions at request time.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\PersonalAccessTokenRepository")
 *
 * @ORM\Table(name="personal_access_token", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="pat_token_prefix_unique", columns={"token_prefix"})
 * })
 */
class PersonalAccessToken extends CoreEntity implements UuidEntityInterface
{
    public const TOKEN_PREFIX_LENGTH = 12;
    public const TOKEN_LITERAL_PREFIX = 'dplan_pat_';
    public const MAX_LIFETIME_DAYS = 365;
    public const DEFAULT_LIFETIME_DAYS = 90;

    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected ?string $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="user", referencedColumnName="_u_id", nullable=false, onDelete="CASCADE")
     */
    protected User $user;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(name="customer", referencedColumnName="_c_id", nullable=false, onDelete="CASCADE")
     */
    protected Customer $customer;

    /**
     * @ORM\Column(type="string", length=120, nullable=false)
     */
    protected string $name;

    /**
     * @ORM\Column(name="token_prefix", type="string", length=12, nullable=false)
     */
    protected string $tokenPrefix;

    /**
     * @ORM\Column(name="token_hash", type="string", length=255, nullable=false)
     */
    protected string $tokenHash;

    /**
     * @var list<string>
     *
     * @ORM\Column(type="json", nullable=false)
     */
    protected array $scopes = [];

    /**
     * When non-empty, restricts procedure-scoped resource access to these procedure IDs.
     *
     * @var list<string>|null
     *
     * @ORM\Column(name="procedure_ids", type="json", nullable=true)
     */
    protected ?array $procedureIds = null;

    /**
     * @ORM\Column(name="expires_at", type="datetime", nullable=false)
     */
    protected DateTime $expiresAt;

    /**
     * @ORM\Column(name="last_used_at", type="datetime", nullable=true)
     */
    protected ?DateTime $lastUsedAt = null;

    /**
     * @ORM\Column(name="revoked_at", type="datetime", nullable=true)
     */
    protected ?DateTime $revokedAt = null;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="revoked_by", referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     */
    protected ?User $revokedBy = null;

    /**
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected DateTime $createdAt;

    /**
     * @param list<string>      $scopes
     * @param list<string>|null $procedureIds
     */
    public function __construct(
        User $user,
        Customer $customer,
        string $name,
        string $tokenPrefix,
        string $tokenHash,
        array $scopes,
        DateTime $expiresAt,
        ?array $procedureIds = null,
    ) {
        $this->user = $user;
        $this->customer = $customer;
        $this->name = $name;
        $this->tokenPrefix = $tokenPrefix;
        $this->tokenHash = $tokenHash;
        $this->scopes = array_values($scopes);
        $this->expiresAt = $expiresAt;
        $this->procedureIds = null === $procedureIds ? null : array_values($procedureIds);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTokenPrefix(): string
    {
        return $this->tokenPrefix;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @return list<string>|null */
    public function getProcedureIds(): ?array
    {
        return $this->procedureIds;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function getLastUsedAt(): ?DateTime
    {
        return $this->lastUsedAt;
    }

    public function getRevokedAt(): ?DateTime
    {
        return $this->revokedAt;
    }

    public function getRevokedBy(): ?User
    {
        return $this->revokedBy;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function markUsed(DateTime $now): void
    {
        $this->lastUsedAt = $now;
    }

    public function revoke(DateTime $now, ?User $by = null): void
    {
        if (null !== $this->revokedAt) {
            return;
        }
        $this->revokedAt = $now;
        $this->revokedBy = $by;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(DateTime $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isActive(DateTime $now): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }
}
