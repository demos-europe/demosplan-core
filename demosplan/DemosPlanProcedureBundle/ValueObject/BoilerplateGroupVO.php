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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Symfony\Component\Validator\Constraints as Assert;

class BoilerplateGroupVO extends ValueObject
{
    /** @var string */
    protected $id;

    /**
     * @var string
     *
     * @Assert\NotBlank(message = "Der Titel der Gruppe darf nicht leer sein")
     */
    protected $title;

    /** @var Boilerplate[] */
    protected $boilerplates;

    /** @var Procedure */
    protected $procedure;

    /**
     * Create a BoilerplateGroupVO from an BoilerplateGroup if $group is given, otherwise this is a simple empty constructor.
     *
     * @param BoilerplateGroup|null $group create a BoilerplateGroupVO from if given, otherwise this is a simple empty constructor
     */
    public function __construct(BoilerplateGroup $group = null)
    {
        if (null !== $group) {
            $this->generateFromBoilerplateGroup($group);
        }
    }

    /**
     * Generates a BoilerplateGroupVO from incoming BoilerplateGroup and lock this.
     *
     * @param BoilerplateGroup $group group, which will be used to generate this BoilerplateVO
     */
    public function generateFromBoilerplateGroup(BoilerplateGroup $group)
    {
        $this->setId($group->getId());
        $this->setTitle($group->getTitle());
        $this->setBoilerplates($group->getBoilerplates()->toArray());
        $this->setProcedure($group->getProcedure());
        $this->lock();
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
     * @return Boilerplate[]
     */
    public function getBoilerplates(): array
    {
        return $this->boilerplates;
    }

    /**
     * @param Boilerplate[] $boilerplates
     */
    public function setBoilerplates(array $boilerplates)
    {
        $this->boilerplates = $boilerplates;
    }

    public function setProcedure(Procedure $procedure): BoilerplateGroupVO
    {
        $this->procedure = $procedure;

        return $this;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }
}
