<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Workflow;

use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\SortableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'workflow_place')]
#[ORM\UniqueConstraint(name: 'unique_workflow_place_name', columns: ['name', 'procedure_id'])]
#[ORM\UniqueConstraint(name: 'unique_workflow_place_sort_index', columns: ['sort_index', 'procedure_id'])]
#[ORM\Entity(repositoryClass: PlaceRepository::class)]
class Place extends CoreEntity implements SortableInterface, PlaceInterface
{
    /**
     * The displayed description of this instance.
     *
     * @var string
     */
    #[Assert\NotNull]
    #[Assert\Length(min: 0, max: 255, normalizer: 'trim')]
    #[ORM\Column(type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    private $description = '';
    #[ORM\Column(name: 'solved', type: 'boolean', nullable: false, options: ['default' => false, 'fixed' => true])]
    private bool $solved = false;

    public function __construct(
        #[Assert\NotNull] #[ORM\JoinColumn(referencedColumnName: '_p_id', nullable: false)] #[ORM\ManyToOne(targetEntity: Procedure::class, inversedBy: 'segmentPlaces')]
        private Procedure $procedure,
        /**
         * The displayed name of this instance.
         */
        #[Assert\NotBlank(normalizer: 'trim', allowNull: false)]
        #[Assert\Length(min: 1, max: 255, normalizer: 'trim')] #[ORM\Column(type: 'string', length: 255, nullable: false)]
        private string $name = '',
        #[Assert\NotNull] #[ORM\Column(type: 'integer', nullable: false, options: ['unsigned' => true, 'default' => 0])]
        private int $sortIndex = 0,
        /**
         * @var string|null `null` if this instance was not persisted yet
         *
         *
         *
         *
         */
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'CUSTOM')]
        #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
        #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
        private ?string $id = null,
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setSortIndex(int $sortIndex): self
    {
        $this->sortIndex = $sortIndex;

        return $this;
    }

    public function getSortIndex(): int
    {
        return $this->sortIndex;
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description)
    {
        $this->description = $description;

        return $this;
    }

    public function getSolved(): bool
    {
        return $this->solved;
    }

    public function setSolved(bool $solved): self
    {
        $this->solved = $solved;

        return $this;
    }
}
