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
 * Differs from {@see \demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken} in three ways
 * that are the point of the class: the subject is a procedure rather than a user, so the restriction
 * is mandatory; {@see self::$createdBy} is audit only and nullable, so the integration survives the
 * planner who paired it leaving; and there is no expiry, only revocation.
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
     * Audit trail only: it grants nothing, and the token outlives this user.
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
        $this->scopes = $scopes;
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
     * Never absent, unlike a PAT's allowlist, so callers need no null branch.
     */
    public function allowsProcedure(string $procedureId): bool
    {
        return $this->procedure->getId() === $procedureId;
    }
}
