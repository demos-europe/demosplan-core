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
use DemosEurope\DemosplanAddon\Contracts\Entities\HashedQueryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\StoredQuery\StoredQueryInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @see https://yaits.demos-deutschland.de/w/demosplan/functions/filterhash/ Wiki: Filterhash
 *
 * @ORM\Table(indexes={@ORM\Index(name="hash_idx", columns={"hash"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\HashedQueryRepository")
 */
class HashedQuery extends CoreEntity implements UuidEntityInterface, HashedQueryInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
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
     * @ORM\Column(type="string", nullable=false, length=12, unique=true)
     */
    protected $hash;

    /**
     * @var StoredQueryInterface
     *
     * @ORM\Column(type="dplan.stored_query", nullable=false)
     */
    protected $storedQuery;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $created;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $modified;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=false, onDelete="NO ACTION")
     */
    protected $procedure;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getStoredQuery(): StoredQueryInterface
    {
        return $this->storedQuery;
    }

    public function setStoredQuery(StoredQueryInterface $query): void
    {
        $this->storedQuery = $query;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    protected function setCreated(DateTime $created): void
    {
        $this->created = $created;
    }

    public function getModified(): DateTime
    {
        return $this->modified;
    }

    public function setModified(DateTime $date): void
    {
        $this->modified = $date;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function setProcedure(Procedure $procedure): void
    {
        $this->procedure = $procedure;
    }
}
