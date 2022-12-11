<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(indexes={@ORM\Index(columns={"entity"}), @ORM\Index(columns={"created"})})
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SearchIndexTaskRepository")
 */
class SearchIndexTask extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    protected $entity;

    /**
     * @var string
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     */
    protected $entityId;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $userId;

    /**
     * @var DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime", nullable=false)
     */
    protected $created;

    /**
     * Is this entry currently processed?
     *
     * @var bool
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $processing = false;

    public function __construct(string $entityClass, string $entityId, string $userId = null, bool $processing = false)
    {
        $this->entity = $entityClass;
        $this->entityId = $entityId;
        $this->userId = $userId;
        $this->processing = $processing;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->entity;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * @return string|null
     */
    public function getUserId()
    {
        return $this->userId;
    }

    public function isProcessing(): bool
    {
        return $this->processing;
    }
}
