<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\EntityContentChangeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository")
 */
class EntityContentChange extends CoreEntity implements UuidEntityInterface, EntityContentChangeInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

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
     * No relation, to avoid difficulties on deleting user.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $userId;

    /**
     * Name of User of userId, for simple access and rendering.
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $userName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $entityType;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $entityId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $entityField;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true, length=15000000)
     */
    protected $preUpdate;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true, length=15000000)
     */
    protected $postUpdate;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true, length=15000000)
     */
    protected $contentChange;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param DateTime $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @return DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @param DateTime $modified
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param string $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return string
     */
    public function getEntityType()
    {
        return $this->entityType;
    }

    /**
     * @param string $entityType
     */
    public function setEntityType($entityType)
    {
        $this->entityType = $entityType;
    }

    /**
     * @return string
     */
    public function getEntityId()
    {
        return $this->entityId;
    }

    /**
     * @param string $entityId
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;
    }

    /**
     * @return string
     */
    public function getEntityField()
    {
        return $this->entityField;
    }

    /**
     * @param string $entityField
     */
    public function setEntityField($entityField)
    {
        $this->entityField = $entityField;
    }

    /**
     * @return int|string|bool|null
     */
    public function getPreUpdate()
    {
        return $this->preUpdate;
    }

    /**
     * @param int|string|bool|null $preUpdate
     */
    public function setPreUpdate($preUpdate)
    {
        $this->preUpdate = $preUpdate;
    }

    /**
     * @return int|string|bool|null
     */
    public function getPostUpdate()
    {
        return $this->postUpdate;
    }

    /**
     * @param int|string|bool|null $postUpdate
     */
    public function setPostUpdate($postUpdate)
    {
        $this->postUpdate = $postUpdate;
    }

    public function getContentChange(): string
    {
        return $this->contentChange;
    }

    public function setContentChange(string $contentChange)
    {
        $this->contentChange = $contentChange;
    }

    /**
     * @return string
     */
    public function getUserName()
    {
        return $this->userName;
    }

    /**
     * @param string $userName
     */
    public function setUserName($userName)
    {
        $this->userName = $userName;
    }
}
