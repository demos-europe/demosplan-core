<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links the user, the role and the customer (currently only relevant for the CustomerMasterUser).
 *
 * @ORM\Table(name="relation_customer_orga_orga_type",
 *    uniqueConstraints={
 *
 *        @ORM\UniqueConstraint(name="o_c_ot_unique",
 *            columns={"_o_id", "_c_id", "_ot_id"})
 *    }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\OrgaStatusInCustomerRepository")
 */
class OrgaStatusInCustomer extends CoreEntity implements UuidEntityInterface, OrgaStatusInCustomerInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * Foreign key, Orga object.
     *
     * @var OrgaInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", inversedBy="statusInCustomers", cascade={"remove"})
     *
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", nullable=false, onDelete="CASCADE")
     */
    protected $orga;

    /**
     * Foreign key, Orga Type object.
     *
     * @var OrgaTypeInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\OrgaType", inversedBy="orgaStatusInCustomers", cascade={"remove"})
     *
     * @ORM\JoinColumn(name="_ot_id", referencedColumnName="_ot_id", nullable=false)
     */
    protected $orgaType;

    /**
     * Foreign key, Customer object.
     *
     * @var CustomerInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer", inversedBy="orgaStatuses", cascade={"remove"})
     *
     * @ORM\JoinColumn(name="_c_id", referencedColumnName="_c_id", nullable=false)
     */
    protected $customer;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $status;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    public function getOrga(): Orga
    {
        return $this->orga;
    }

    public function setOrga(OrgaInterface $orga)
    {
        $this->orga = $orga;
    }

    public function getOrgaType(): OrgaType
    {
        return $this->orgaType;
    }

    public function setOrgaType(OrgaTypeInterface $orgaType)
    {
        $this->orgaType = $orgaType;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(CustomerInterface $customer)
    {
        $this->customer = $customer;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status)
    {
        $this->status = match ($status) {
            OrgaStatusInCustomerInterface::STATUS_ACCEPTED, OrgaStatusInCustomerInterface::STATUS_REJECTED, OrgaStatusInCustomerInterface::STATUS_PENDING => $status,
            default => throw new InvalidArgumentException("Invalid status {$status}"),
        };
    }
}
