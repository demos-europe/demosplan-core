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
use DemosEurope\DemosplanAddon\Contracts\Entities\FileContainerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="file_container",indexes={@ORM\Index(columns={"entity_id", "entity_class"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository")
 */
class FileContainer extends CoreEntity implements UuidEntityInterface, FileContainerInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;


    /**
     * @var Statement\Statement|null
     */
    protected $statement;

    /**
     * @var string
     *
     * @ORM\Column(name="entity_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $entityId;



    /**
     * @var string
     *
     * @ORM\Column(name="entity_class", type="string", options={"fixed":true}, nullable=false)
     */
    protected $entityClass;

    /**
     * @var string
     *
     * @ORM\Column(name="entity_field", type="string", nullable=false)
     */
    protected $entityField;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var int
     *
     * @ORM\Column(name="orderNum", type="smallint", length=3, nullable=false, options={"unsigned":true})
     */
    protected $order = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="file_string", type="string", length=2048, nullable=false)
     */
    protected $fileString;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="file_id", referencedColumnName="_f_ident", nullable=false, onDelete="CASCADE")
     */
    protected $file;

    /**
     * Is the file visible in this statement for other users than Fachplaner (yes = true, no = false).
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true, "comment":"Is the file visible in this statement for other users than Fachplaner"})
     */
    protected $publicAllowed = true;

    public function getId(): ?string
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
     * @return string
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * @param string $entityClass
     */
    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;
    }

    /**
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;
    }

    /**
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param int $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @return string
     */
    public function getFileString()
    {
        return $this->fileString;
    }

    /**
     * @param string $fileString
     */
    public function setFileString($fileString)
    {
        $this->fileString = $fileString;
    }

    /**
     * @return FileInterface
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param FileInterface $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * @param bool $publicAllowed
     *
     * @return $this
     */
    public function setPublicAllowed($publicAllowed)
    {
        $this->publicAllowed = $publicAllowed;

        return $this;
    }

    /**
     * @return bool
     */
    public function getPublicAllowed()
    {
        return $this->publicAllowed;
    }

}
