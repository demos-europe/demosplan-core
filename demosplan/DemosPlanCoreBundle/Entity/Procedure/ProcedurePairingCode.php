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
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePairingCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Short-lived, single-use code that another instance exchanges for a durable
 * {@see ProcedureIntegrationToken}, so the durable secret never travels through mail or chat.
 *
 * Not an {@see \demosplan\DemosPlanCoreBundle\Entity\User\AbstractApiToken}: it authenticates
 * nothing and has no prefix/secret split, because a human has to be able to transcribe it.
 */
#[ORM\Entity(repositoryClass: ProcedurePairingCodeRepository::class)]
#[ORM\Table(name: 'procedure_pairing_code')]
#[ORM\UniqueConstraint(name: 'procedure_pairing_code_hash_unique', columns: ['code_hash'])]
class ProcedurePairingCode extends CoreEntity implements UuidEntityInterface
{
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected ?string $id = null;

    /**
     * The procedure the exchanged token will be pinned to.
     */
    #[ORM\ManyToOne(targetEntity: Procedure::class)]
    #[ORM\JoinColumn(name: 'procedure_id', referencedColumnName: '_p_id', nullable: false, onDelete: 'CASCADE')]
    protected Procedure $procedure;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: '_c_id', nullable: false, onDelete: 'CASCADE')]
    protected Customer $customer;

    /**
     * Audit only, and nullable so the code survives its issuer being deleted.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: '_u_id', nullable: true, onDelete: 'SET NULL')]
    protected ?User $createdBy = null;

    /**
     * Digest of the normalised code; the plaintext is shown once and never persisted.
     */
    #[ORM\Column(name: 'code_hash', type: 'string', length: 64, nullable: false)]
    protected string $codeHash;

    /**
     * Label carried over to the exchanged token.
     */
    #[ORM\Column(type: 'string', length: 120, nullable: false)]
    protected string $name;

    /**
     * Fixed at issue time, so redeeming cannot widen what the pairing was meant to allow.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json', nullable: false)]
    protected array $scopes = [];

    /**
     * Not nullable, unlike a token's: a code that never expires defeats its purpose.
     */
    #[ORM\Column(name: 'expires_at', type: 'datetime', nullable: false)]
    protected DateTime $expiresAt;

    #[ORM\Column(name: 'consumed_at', type: 'datetime', nullable: true)]
    protected ?DateTime $consumedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
    protected DateTime $createdAt;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        Procedure $procedure,
        Customer $customer,
        string $name,
        string $codeHash,
        array $scopes,
        DateTime $expiresAt,
        ?User $createdBy = null,
    ) {
        $this->procedure = $procedure;
        $this->customer = $customer;
        $this->name = $name;
        $this->codeHash = $codeHash;
        $this->scopes = $scopes;
        $this->expiresAt = $expiresAt;
        $this->createdBy = $createdBy;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?DateTime
    {
        return $this->consumedAt;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function isExpired(DateTime $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isRedeemable(DateTime $now): bool
    {
        return !$this->isConsumed() && !$this->isExpired($now);
    }

    /**
     * Call only after the database confirmed this process won the race for the code.
     */
    public function markConsumed(DateTime $now): void
    {
        $this->consumedAt ??= $now;
    }
}
