<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ManualListSortInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\ORM\Mapping as ORM;

use function is_string;

/**
 * @ORM\Table(name="_manual_list_sort")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository")
 */
class ManualListSort extends CoreEntity implements UuidEntityInterface, ManualListSortInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_mls_id", type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(name="_p_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $pId;

    /**
     * @var string
     *
     * @ORM\Column(name="_mls_context", type="string", length=255, nullable=false)
     */
    protected $context;

    /**
     * @var string
     *
     * @ORM\Column(name="_mls_namespace", type="string", length=255, nullable=false)
     */
    protected $namespace;


    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="_c_id", onDelete="CASCADE", nullable=true)
     */
    protected CustomerInterface $customer;

    /**
     * @var string
     *
     * @ORM\Column(name="_mls_idents", type="text", length=65535, nullable=false)
     */
    protected $idents;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set pId.
     *
     * @param string $pId
     *
     * @return ManualListSort
     */
    public function setPId($pId)
    {
        $this->pId = $pId;

        return $this;
    }

    /**
     * Get pId.
     *
     * @return string
     */
    public function getPId()
    {
        return $this->pId;
    }

    /**
     * Set Context.
     *
     * @param string $context
     *
     * @return ManualListSort
     */
    public function setContext($context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get Context.
     *
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    public function setCustomer(CustomerInterface $customer): ManualListSort
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Set Namespace.
     *
     * @param string $namespace
     *
     * @return ManualListSort
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get Namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Set Idents.
     *
     * @param string $idents
     *
     * @return ManualListSort
     */
    public function setIdents($idents)
    {
        $this->idents = $idents;

        return $this;
    }

    /**
     * Get Idents.
     *
     * @return string
     */
    public function getIdents()
    {
        return $this->idents;
    }

    /**
     * @return array<int, string>
     */
    public function getIdentsArray(): array
    {
        return explode(',', $this->idents);
    }

    /**
     * @param array<int, string> $idents
     */
    public function setIdentsArray(array $idents): self
    {
        foreach ($idents as $ident) {
            if (!is_string($ident)) {
                throw new InvalidArgumentException('Invalid ident. Must be a string.');
            }
            if ('' === $ident) {
                throw new InvalidArgumentException('Invalid ident. Must not be an empty string.');
            }
        }
        $this->idents = implode(',', $idents);

        return $this;
    }
}
