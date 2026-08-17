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
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Everything a hashed, scoped, revocable API credential needs regardless of what it authenticates
 * as. Subclasses add their subject: {@see PersonalAccessToken} acts for a user, the procedure
 * integration token acts for a single procedure.
 *
 * The client presents `<literal prefix><prefix><secret>`: the prefix is stored in the clear so a
 * lookup can find the row without the secret, the secret is only ever stored hashed, and the literal
 * prefix is per subclass so the kinds cannot be confused in logs.
 *
 * A mapped superclass rather than an inheritance hierarchy, since each subclass has its own table and
 * therefore its own token-prefix unique constraint.
 */
#[ORM\MappedSuperclass]
abstract class AbstractApiToken extends CoreEntity
{
    public const TOKEN_PREFIX_LENGTH = 12;

    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer', referencedColumnName: '_c_id', nullable: false, onDelete: 'CASCADE')]
    protected Customer $customer;

    #[ORM\Column(type: 'string', length: 120, nullable: false)]
    protected string $name;

    #[ORM\Column(name: 'token_prefix', type: 'string', length: 12, nullable: false)]
    protected string $tokenPrefix;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 255, nullable: false)]
    protected string $tokenHash;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json', nullable: false)]
    protected array $scopes = [];

    #[ORM\Column(name: 'expires_at', type: 'datetime', nullable: true)]
    protected ?DateTime $expiresAt = null;

    #[ORM\Column(name: 'last_used_at', type: 'datetime', nullable: true)]
    protected ?DateTime $lastUsedAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime', nullable: true)]
    protected ?DateTime $revokedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'revoked_by', referencedColumnName: '_u_id', nullable: true, onDelete: 'SET NULL')]
    protected ?User $revokedBy = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
    protected DateTime $createdAt;

    /**
     * The literal prefix every token string of this kind starts with, e.g. `dplan_pat_`.
     */
    abstract public static function literalPrefix(): string;

    public function getId(): ?string
    {
        return $this->id;
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

    public function getExpiresAt(): ?DateTime
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

    /**
     * A token without an expiry date never expires; it can only be revoked.
     */
    public function isExpired(DateTime $now): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= $now;
    }

    public function isActive(DateTime $now): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }
}
