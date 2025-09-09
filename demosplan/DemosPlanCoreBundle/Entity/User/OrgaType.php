<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="_orga_type")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository")
 */
class OrgaType extends CoreEntity implements UuidEntityInterface, OrgaTypeInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_ot_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string things like {@link OrgaTypeInterface::MUNICIPALITY}
     *
     * @ORM\Column(name="_ot_name", type="string", length=6, nullable=false, options={"fixed":true})
     */
    protected $name;

    /**
     * @var string things like "Institution"
     *
     * @ORM\Column(name="_ot_label", type="string", length=45, nullable=false)
     */
    protected $label;

    /**
     * @var Collection<int, OrgaStatusInCustomerInterface>
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer", mappedBy="orgaType")
     */
    protected $orgaStatusInCustomers;

    public function __construct()
    {
        $this->orgaStatusInCustomers = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set otName.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get otName.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set otLabel.
     *
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * Get otLabel.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return Collection<int, OrgaStatusInCustomerInterface>
     */
    public function getOrgaStatusInCustomers()
    {
        return $this->orgaStatusInCustomers;
    }

    /**
     * @param Collection<int, OrgaStatusInCustomerInterface> $orgaStatusInCustomers
     */
    public function setOrgaStatusInCustomers(Collection $orgaStatusInCustomers)
    {
        $this->orgaStatusInCustomers = $orgaStatusInCustomers;
    }
}
