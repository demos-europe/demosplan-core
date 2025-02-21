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
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_predefined_texts_category")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\BoilerplateCategoryRepository")
 */
class BoilerplateCategory extends CoreEntity implements UuidEntityInterface, BoilerplateCategoryInterface
{
    /**
     * Unique identification of the boilerplate entry.
     *
     * @var string|null
     *
     * @ORM\Column(name="ptc_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var ProcedureInterface
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var Collection<int, BoilerplateInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate", inversedBy="categories")
     *
     * @ORM\JoinTable(
     *     name="predefined_texts_categories",
     *     joinColumns={@ORM\JoinColumn(name="_ptc_id", referencedColumnName="ptc_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_pt_id", referencedColumnName="_pt_id", onDelete="CASCADE")}
     * )
     */
    protected $boilerplates;

    /**
     * @var string
     *
     * @ORM\Column(name="ptc_title", type="string", length=255, nullable=true)
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="ptc_text", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="ptc_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="ptc_modify_date",type="datetime", nullable=false)
     */
    protected $modifyDate;

    public function __construct()
    {
        $this->boilerplates = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPId()
    {
        return $this->procedure->getId();
    }

    /**
     * @return ProcedureInterface
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @param ProcedureInterface $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return DateTime
     */
    public function getCreateDate()
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

    /**
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;
    }

    /**
     * Add a given Boilerplate to this BoilerplateCategory.
     *
     * @param BoilerplateInterface $bp
     *
     * @return BoilerplateCategoryInterface
     */
    public function addBoilerplate($bp)
    {
        if (!$this->boilerplates->contains($bp) && $bp->getProcedure() == $this->procedure) {
            $this->boilerplates->add($bp);
            $bp->addBoilerplateCategory($this);
        }

        return $this;
    }

    /**
     * Remove the given Boilerplate from this BoilerplateCategory.
     *
     * @param BoilerplateInterface $boilerplate
     *
     * @return BoilerplateCategoryInterface
     */
    public function removeBoilerplate($boilerplate)
    {
        if ($this->boilerplates->contains($boilerplate)) {
            $this->boilerplates->removeElement($boilerplate);
            $boilerplate->removeBoilerplateCategory($this);
        }

        return $this;
    }

    /**
     * Return the Boilerplates attached to this BoilerplateCategory.
     *
     * @return Collection<int, BoilerplateInterface>
     */
    public function getBoilerplates()
    {
        return $this->boilerplates;
    }

    /**
     * @param array|ArrayCollection $boilerplates
     */
    public function setBoilerplates($boilerplates)
    {
        $this->boilerplates = is_array($boilerplates) ? new ArrayCollection($boilerplates) : $boilerplates;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}
