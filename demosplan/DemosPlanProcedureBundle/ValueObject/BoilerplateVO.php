<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

class BoilerplateVO extends ValueObject
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    #[Assert\NotBlank(message: 'boilerplate.title.not.blank')]
    #[Assert\Length(min: 1, max: 250, minMessage: 'boilerplate.title.min.length', maxMessage: 'boilerplate.title.max.length')]
    protected $title;

    /**
     * @var ArrayCollection
     */
    protected $categories;

    /** @var BoilerplateGroupVO */
    protected $group;

    /**
     * @var string
     */
    #[Assert\NotBlank(message: 'Der Text darf nicht leer sein')]
    protected $text;

    /** @var Procedure */
    protected $procedure;

    public function __construct(Boilerplate $boilerplate = null)
    {
        $this->categories = new ArrayCollection();
        if (null !== $boilerplate) {
            $this->setId($boilerplate->getId());
            $this->setTitle($boilerplate->getTitle());
            $this->setText($boilerplate->getText());
            $this->setCategories($boilerplate->getCategories());
            $this->setProcedure($boilerplate->getProcedure());
            $this->setGroup($boilerplate->getGroup());
            $this->lock();
        }
    }

    /**
     * @param ArrayCollection|BoilerplateCategoryVO[]|string $category
     */
    public function addCategory($category)
    {
        $this->categories->add($category);
    }

    /**
     * @param BoilerplateCategory $category
     */
    public function removeCategory($category)
    {
        $this->categories->remove($category);
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return ArrayCollection|BoilerplateCategoryVO[]
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @param ArrayCollection $categories
     */
    public function setCategories($categories)
    {
        $this->categories = $categories;
    }

    /**
     * @return mixed
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param mixed $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return Procedure
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * Excepects Procedure instead of ProcedureVO, because Procedure of Boilerplate is not editable (yet).
     *
     * @param Procedure $procedure
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;
    }

    /**
     * @return BoilerplateGroupVO|null
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @return string|null
     */
    public function getGroupId()
    {
        return null === $this->group ? null : $this->group->getId();
    }

    /**
     * @param BoilerplateGroupVO $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @param string|null $groupId
     */
    public function setGroupId($groupId)
    {
        $this->group = $groupId;
    }
}
