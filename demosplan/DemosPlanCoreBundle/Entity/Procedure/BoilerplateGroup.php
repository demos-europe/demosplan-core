<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateGroupInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\BoilerplateGroupRepository")
 */
class BoilerplateGroup extends CoreEntity implements UuidEntityInterface, BoilerplateGroupInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $title = '';

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createDate;

    /**
     * @var ProcedureInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable = false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var Collection<int, BoilerplateInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate", mappedBy = "group")
     *
     * @ORM\OrderBy({"title" = "ASC"})
     */
    protected $boilerplates;

    /**
     * @param string             $title
     * @param ProcedureInterface $procedure
     */
    public function __construct($title, $procedure)
    {
        $this->boilerplates = new ArrayCollection();
        $this->setTitle($title);
        $this->setProcedure($procedure);
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    public function getCreateDate(): DateTime
    {
        return $this->createDate;
    }

    /**
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }

    public function setProcedure(ProcedureInterface $procedure)
    {
        $this->procedure = $procedure;

        $boilerplates = $this->getBoilerplates();
        foreach ($boilerplates as $boilerplate) {
            $boilerplate->setProcedure($procedure);
        }
    }

    /**
     * @return string[]
     */
    public function getCategoryTitles(): array
    {
        $categoryTitles = [];
        /** @var BoilerplateInterface $boilerplate */
        foreach ($this->getBoilerplates() as $boilerplate) {
            foreach ($boilerplate->getCategoryTitles() as $title) {
                $categoryTitles[] = $title;
            }
        }

        return array_unique($categoryTitles);
    }

    /**
     * @param string $categoryTitle
     *
     * @return BoilerplateInterface[]
     */
    public function filterBoilerplatesByCategory($categoryTitle): array
    {
        $resultSet = [];
        /** @var BoilerplateInterface $boilerplate */
        foreach ($this->getBoilerplates() as $boilerplate) {
            if ($boilerplate->hasCategory($categoryTitle)) {
                $resultSet[] = $boilerplate;
            }
        }

        return $resultSet;
    }

    /**
     * @return ArrayCollection
     */
    public function getBoilerplates()
    {
        return $this->boilerplates;
    }

    /**
     * Returns a specific Boilerplate of this group, if exists.
     *
     * @param string $id identifies the Boilerplate
     *
     * @return BoilerplateInterface|null
     */
    public function getBoilerplate(string $id)
    {
        $allBoilerplates = $this->getBoilerplates()->getValues();

        foreach ($allBoilerplates as $boilerplate) {
            if ($boilerplate->getId() === $id) {
                return $boilerplate;
            }
        }

        return null;
    }

    /**
     * Add a specific Boilerplate to this BoilerplateGroup.
     */
    public function addBoilerplate(BoilerplateInterface $boilerplate): BoilerplateGroupInterface
    {
        if (!$this->boilerplates->contains($boilerplate)) {
            $this->boilerplates->add($boilerplate);
        }

        if ($boilerplate->getGroup() !== $this) {
            $boilerplate->setGroup($this);
        }

        return $this;
    }

    /**
     * Removes a specific Boilerplate from this BoilerplateGroup.
     */
    public function removeBoilerplate(BoilerplateInterface $boilerplate): BoilerplateGroupInterface
    {
        $this->boilerplates->removeElement($boilerplate);
        if ($boilerplate->hasGroup()) {
            $boilerplate->detachGroup();
        }

        return $this;
    }

    /**
     * @param BoilerplateInterface[] $boilerplates
     */
    public function removeBoilerplates(array $boilerplates)
    {
        foreach ($boilerplates as $boilerplate) {
            $this->removeBoilerplate($boilerplate);
        }
    }

    /**
     * Removes all Boilerplates from this BoilerplateGroup.
     *
     * @return $this
     */
    public function removeAllBoilerplates(): BoilerplateGroupInterface
    {
        foreach ($this->getBoilerplates() as $boilerplate) {
            $boilerplate->detachGroup();
        }
        $this->boilerplates->clear();

        return $this;
    }

    /**
     * @param array|ArrayCollection $boilerplates
     */
    public function setBoilerplates($boilerplates)
    {
        $this->boilerplates = new ArrayCollection();
        $this->addBoilerplates($boilerplates);
    }

    /**
     * @param array|ArrayCollection $boilerplates
     */
    public function addBoilerplates($boilerplates)
    {
        foreach ($boilerplates as $boilerplate) {
            $this->addBoilerplate($boilerplate);
        }
    }

    /**
     * Checks if this Group has Boilerplates.
     *
     * @return bool - true, if this there are no boilerpaltes, otherwise false
     */
    public function isEmpty(): bool
    {
        return 0 === count($this->getBoilerplates());
    }
}
