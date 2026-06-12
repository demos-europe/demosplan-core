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
use demosplan\DemosPlanCoreBundle\Repository\ProcedureDeletionLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProcedureDeletionLogRepository::class)]
class ProcedureDeletionLog implements UuidEntityInterface
{
    final public const DELETE_TYPE_SOFT = 'soft';
    final public const DELETE_TYPE_HARD = 'hard';

    /**
     * @var string|null `null` if this instance was created but not persisted yet
     */
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private $id;

    /**
     * Set while the procedure still exists; nulled before the procedure is hard-deleted
     * so the FK constraint does not fire during purge.
     *
     * @var Procedure|null
     */
    #[ORM\JoinColumn(name: 'procedure_fk', referencedColumnName: '_p_id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: Procedure::class)]
    private $procedure;

    /**
     * Snapshot of the procedure ID — remains populated after the FK is nulled.
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    private $procedureId;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', length: 4096)]
    private $procedureName;

    /**
     * @var bool
     */
    #[ORM\Column(type: 'boolean')]
    private $isBlueprint;

    /**
     * Snapshot — not a FK so the log survives user-account deletion.
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 36, nullable: true, options: ['fixed' => true])]
    private $deletedByUserId;

    /**
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $deletedByUserFirstName;

    /**
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $deletedByUserLastName;

    /**
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $deletedByUserEmail;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', length: 10)]
    private $deleteType;

    /**
     * @var DateTime
     */
    #[ORM\Column(type: 'datetime')]
    private $deletedAt;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProcedure(): ?Procedure
    {
        return $this->procedure;
    }

    public function setProcedure(?Procedure $procedure): self
    {
        $this->procedure = $procedure;

        return $this;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setProcedureId(string $procedureId): self
    {
        $this->procedureId = $procedureId;

        return $this;
    }

    public function getProcedureName(): string
    {
        return $this->procedureName;
    }

    public function setProcedureName(string $procedureName): self
    {
        $this->procedureName = $procedureName;

        return $this;
    }

    public function isBlueprint(): bool
    {
        return $this->isBlueprint;
    }

    public function setIsBlueprint(bool $isBlueprint): self
    {
        $this->isBlueprint = $isBlueprint;

        return $this;
    }

    public function getDeletedByUserId(): ?string
    {
        return $this->deletedByUserId;
    }

    public function setDeletedByUserId(?string $deletedByUserId): self
    {
        $this->deletedByUserId = $deletedByUserId;

        return $this;
    }

    public function getDeletedByUserFirstName(): ?string
    {
        return $this->deletedByUserFirstName;
    }

    public function setDeletedByUserFirstName(?string $deletedByUserFirstName): self
    {
        $this->deletedByUserFirstName = $deletedByUserFirstName;

        return $this;
    }

    public function getDeletedByUserLastName(): ?string
    {
        return $this->deletedByUserLastName;
    }

    public function setDeletedByUserLastName(?string $deletedByUserLastName): self
    {
        $this->deletedByUserLastName = $deletedByUserLastName;

        return $this;
    }

    public function getDeletedByUserEmail(): ?string
    {
        return $this->deletedByUserEmail;
    }

    public function setDeletedByUserEmail(?string $deletedByUserEmail): self
    {
        $this->deletedByUserEmail = $deletedByUserEmail;

        return $this;
    }

    public function getDeleteType(): string
    {
        return $this->deleteType;
    }

    public function setDeleteType(string $deleteType): self
    {
        $this->deleteType = $deleteType;

        return $this;
    }

    public function getDeletedAt(): DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(DateTime $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
