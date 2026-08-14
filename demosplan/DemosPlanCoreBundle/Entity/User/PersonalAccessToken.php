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
use demosplan\DemosPlanCoreBundle\Repository\PersonalAccessTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

/**
 * Long-lived, scoped, revocable API credential owned by a single user and bound to a single customer.
 *
 * A PAT can never grant a permission the owning user does not already possess; the scope list
 * is intersected with the user's effective permissions at request time.
 *
 * Unlike the shared base, a PAT always expires: the column stays NOT NULL and the service caps the
 * lifetime at {@see self::MAX_LIFETIME_DAYS}.
 */
#[ORM\Entity(repositoryClass: PersonalAccessTokenRepository::class)]
#[ORM\Table(name: 'personal_access_token')]
#[ORM\UniqueConstraint(name: 'pat_token_prefix_unique', columns: ['token_prefix'])]
#[ORM\AttributeOverrides([
    new ORM\AttributeOverride(
        name: 'expiresAt',
        column: new ORM\Column(name: 'expires_at', type: 'datetime', nullable: false)
    ),
])]
class PersonalAccessToken extends AbstractApiToken implements UuidEntityInterface
{
    public const TOKEN_LITERAL_PREFIX = 'dplan_pat_';
    public const MAX_LIFETIME_DAYS = 365;
    public const DEFAULT_LIFETIME_DAYS = 90;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user', referencedColumnName: '_u_id', nullable: false, onDelete: 'CASCADE')]
    protected User $user;

    /**
     * When non-empty, restricts procedure-scoped resource access to these procedure IDs.
     *
     * @var list<string>|null
     */
    #[ORM\Column(name: 'procedure_ids', type: 'json', nullable: true)]
    protected ?array $procedureIds = null;

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

    public static function literalPrefix(): string
    {
        return self::TOKEN_LITERAL_PREFIX;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /** @return list<string>|null */
    public function getProcedureIds(): ?array
    {
        return $this->procedureIds;
    }

    /**
     * Narrowed against the base: a PAT is never created without an expiry date.
     */
    public function getExpiresAt(): DateTime
    {
        Assert::notNull($this->expiresAt);

        return $this->expiresAt;
    }
}
