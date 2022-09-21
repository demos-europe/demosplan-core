<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Workflow;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\SortableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository")
 * @ORM\Table(name="workflow_place", uniqueConstraints={
 *        @ORM\UniqueConstraint(name="unique_workflow_place_name", columns={"name", "procedure_id"}),
 *        @ORM\UniqueConstraint(name="unique_workflow_place_sort_index", columns={"sort_index", "procedure_id"})
 * })
 */
class Place extends CoreEntity implements SortableInterface
{
    /**
     * @var string|null `null` if this instance was not persisted yet
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     */
    private $id;

    /**
     * The displayed name of this instance.
     *
     * @var string
     *
     * @Assert\NotBlank(normalizer="trim", allowNull=false)
     * @Assert\Length(min=1, max=255, normalizer="trim")
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * The displayed description of this instance.
     *
     * @var string
     *
     * @Assert\NotNull()
     * @Assert\Length(min=0, max=255, normalizer="trim")
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"default":""})
     */
    private $description = '';

    /**
     * @var int
     *
     * @Assert\NotNull
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true, "default":0})
     */
    private $sortIndex;

    /**
     * @var Procedure
     *
     * @Assert\NotNull
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", inversedBy="segmentPlaces")
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=false)
     */
    private $procedure;

    public function __construct(Procedure $procedure, string $name = '', int $sortIndex = 0, string $id = null)
    {
        $this->procedure = $procedure;
        $this->name = $name;
        $this->sortIndex = $sortIndex;
        $this->id = $id;
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

    public function getProcedure(): Procedure
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
}
