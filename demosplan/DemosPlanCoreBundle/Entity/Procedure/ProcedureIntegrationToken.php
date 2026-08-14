<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AbstractApiToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureIntegrationTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Credential that lets another demosplan instance write into exactly one procedure of this one.
 *
 * The differences to {@see \demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken} are the
 * point of the class:
 * - The subject is the procedure, not a user. A leaked token buys access to that one procedure and
 *   nothing else, so the restriction is a mandatory relation rather than an optional allowlist.
 * - {@see self::$createdBy} is audit only and nullable: the integration has to keep working after
 *   the planner who paired it leaves, which is also why no deactivation listener may revoke it.
 * - No expiry by default — the inherited column stays nullable, so a token lives until it is
 *   revoked or its procedure is deleted.
 */
#[ORM\Entity(repositoryClass: ProcedureIntegrationTokenRepository::class)]
#[ORM\Table(name: 'procedure_integration_token')]
#[ORM\UniqueConstraint(name: 'procedure_integration_token_prefix_unique', columns: ['token_prefix'])]
class ProcedureIntegrationToken extends AbstractApiToken implements UuidEntityInterface
{
    public const TOKEN_LITERAL_PREFIX = 'dplan_int_';

    #[ORM\ManyToOne(targetEntity: Procedure::class)]
    #[ORM\JoinColumn(name: 'procedure_id', referencedColumnName: '_p_id', nullable: false, onDelete: 'CASCADE')]
    protected Procedure $procedure;

    /**
     * Who paired the integration. Kept for the audit trail only — it grants nothing, and the token
     * outlives this user.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: '_u_id', nullable: true, onDelete: 'SET NULL')]
    protected ?User $createdBy = null;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        Procedure $procedure,
        Customer $customer,
        string $name,
        string $tokenPrefix,
        string $tokenHash,
        array $scopes,
        ?User $createdBy = null,
        ?DateTime $expiresAt = null,
    ) {
        $this->procedure = $procedure;
        $this->customer = $customer;
        $this->name = $name;
        $this->tokenPrefix = $tokenPrefix;
        $this->tokenHash = $tokenHash;
        $this->scopes = array_values($scopes);
        $this->createdBy = $createdBy;
        $this->expiresAt = $expiresAt;
    }

    public static function literalPrefix(): string
    {
        return self::TOKEN_LITERAL_PREFIX;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * The single procedure this token may act on. Unlike a PAT's optional allowlist this can never
     * be absent, so callers do not need a null branch.
     */
    public function allowsProcedure(string $procedureId): bool
    {
        return $this->procedure->getId() === $procedureId;
    }
}
