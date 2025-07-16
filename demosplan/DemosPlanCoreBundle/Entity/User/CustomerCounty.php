<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\CountyInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerCountyInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Links the Customer to their counties and vice-versa.
 *
 * @ORM\Table(
 *     name="customer_county",
 *     uniqueConstraints={
 *
 *         @ORM\UniqueConstraint(
 *             name="customer_county_unique_context",
 *             columns={"customer_id", "county_id"}
 *         )
 *     }
 * )
 *
 * @ORM\Entity
 */
class CustomerCounty extends CoreEntity implements UuidEntityInterface, CustomerCountyInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="cc_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * Foreign key, Customer object.
     *
     * @var CustomerInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer", inversedBy="customerCounties", cascade={"remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="_c_id", nullable=false)
     */
    protected $customer;

    /**
     * Foreign key, County object.
     *
     * @var CountyInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\County", inversedBy="customerCounties", cascade={"remove"})
     *
     * @ORM\JoinColumn(referencedColumnName="_c_id", nullable=false)
     */
    protected $county;

    /**
     * @var string
     *
     * @ORM\Column(type="text", length=255, nullable=false)
     */
    #[Assert\NotNull]
    #[Assert\Email]
    protected $eMailAddress = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(CustomerInterface $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getCounty(): CountyInterface
    {
        return $this->county;
    }

    public function setCounty(CountyInterface $county): self
    {
        $this->county = $county;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->eMailAddress;
    }

    public function setEmail(string $eMail): self
    {
        $this->eMailAddress = $eMail;

        return $this;
    }
}
