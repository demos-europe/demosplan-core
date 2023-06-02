<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

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
     * AHB = Anhörungsbehörde = hearing authority.
     *
     * @const string Denotes a hearing authority agency
     */
    public const HEARING_AUTHORITY_AGENCY = 'OHAUTH';

    /**
     * @const string Denotes a public agency (Institution)
     */
    public const PUBLIC_AGENCY = 'OPSORG';

    /**
     * @const string Denotes a planning agency (Planungsbüro)
     */
    public const PLANNING_AGENCY = 'OPAUTH';

    /**
     * @const string Denotes a municipality (Fachplaner)
     */
    public const MUNICIPALITY = 'OLAUTH';

    /**
     * @const string Default orga type when no other fits
     */
    public const DEFAULT = 'OTDEFA';

    public const ORGATYPE_ROLE = [
        self::PUBLIC_AGENCY            => [
            Role::PUBLIC_AGENCY_COORDINATION,
            Role::PUBLIC_AGENCY_WORKER,
        ],
        self::MUNICIPALITY             => [
            Role::PLANNING_AGENCY_ADMIN,
            Role::PLANNING_AGENCY_WORKER,
        ],
        self::PLANNING_AGENCY          => [
            Role::PRIVATE_PLANNING_AGENCY,
        ],
        self::HEARING_AUTHORITY_AGENCY => [
            Role::HEARING_AUTHORITY_ADMIN,
            Role::HEARING_AUTHORITY_WORKER,
        ],
    ];

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
     * @var string things like {@link OrgaType::MUNICIPALITY}
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
     * @var Collection<int, OrgaStatusInCustomer>
     *
     * @ORM\OneToMany(targetEntity="OrgaStatusInCustomer", mappedBy="orgaType")
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
     * @return Collection<int, OrgaStatusInCustomer>
     */
    public function getOrgaStatusInCustomers()
    {
        return $this->orgaStatusInCustomers;
    }

    /**
     * @param Collection<int, OrgaStatusInCustomer> $orgaStatusInCustomers
     */
    public function setOrgaStatusInCustomers(Collection $orgaStatusInCustomers)
    {
        $this->orgaStatusInCustomers = $orgaStatusInCustomers;
    }
}
