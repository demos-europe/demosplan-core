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
use DemosEurope\DemosplanAddon\Contracts\Entities\CategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_category")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\CategoryRepository")
 */
class Category extends CoreEntity implements UuidEntityInterface, CategoryInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_c_id", type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(name="_c_name", type="string", length=50, nullable=false, options={"default":"custom_category", "comment":"Has no function for custom categories"})
     */
    protected $name = 'custom_category';

    /**
     * @var string
     *
     * @ORM\Column(name="_c_title", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="_c_description", type="text", length=65535, nullable=true, options={"default":null})
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="_c_picture", type="string", length=128, nullable=false, options={"default":""})
     */
    protected $picture = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_c_picture_title", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $pictitle = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_c_enabled", type="boolean", nullable=false, options={"default":true})
     */
    protected $enabled = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="_c_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_c_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_c_modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_c_delete_date", type="datetime", nullable=false)
     */
    protected $deleteDate;

    /**
     * @var Collection<int, GlobalContent>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\GlobalContent", mappedBy="categories")
     */
    protected $globalContents;

    /**
     * Determines if this entry was created by a user or is predefined.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true, "comment":"Determines if this entry was created by a user or is predefined."})
     */
    protected $custom = true;

    public function __construct()
    {
        $this->globalContents = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Category
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return Category
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return Category
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set picture.
     *
     * @param string $picture
     *
     * @return Category
     */
    public function setPicture($picture)
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * Get picture.
     *
     * @return string
     */
    public function getPicture()
    {
        return $this->picture;
    }

    /**
     * Set pictitle.
     *
     * @param string $pictitle
     *
     * @return Category
     */
    public function setPicTitle($pictitle)
    {
        $this->pictitle = $pictitle;

        return $this;
    }

    /**
     * Get pictitle.
     *
     * @return string
     */
    public function getPicTitle()
    {
        return $this->pictitle;
    }

    /**
     * Set enabled.
     *
     * @param bool $enabled
     *
     * @return Category
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get enabled.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->isEnabled();
    }

    /**
     * Get enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return Category
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->isDeleted();
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return Category
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     *
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * Set modifyDate.
     *
     * @param DateTime $modifyDate
     *
     * @return Category
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    /**
     * Get modifyDate.
     *
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * Set deleteDate.
     *
     * @param DateTime $deleteDate
     *
     * @return Category
     */
    public function setDeleteDate($deleteDate)
    {
        $this->deleteDate = $deleteDate;

        return $this;
    }

    /**
     * Get deleteDate.
     *
     * @return DateTime
     */
    public function getDeleteDate()
    {
        return $this->deleteDate;
    }

    /**
     * Set globalContents.
     *
     * @param array $globalContents
     *
     * @return GlobalContent
     */
    public function setGlobalContents($globalContents)
    {
        $this->globalContents = new ArrayCollection($globalContents);

        return $this;
    }

    /**
     * Get globalContents.
     *
     * @return ArrayCollection
     */
    public function getGlobalContents()
    {
        return $this->globalContents;
    }

    public function getGlobalContentsByCustomer(Customer $customer): array
    {
        $globalContentsArray = $this->globalContents->toArray();
        $filteredGlobalContents = array_filter($globalContentsArray, function ($globalContent) use ($customer) {
            return $globalContent->getCustomer() === $customer;
        });

        return $filteredGlobalContents;
    }

    /**
     * @return bool
     */
    public function isCustom()
    {
        return $this->custom;
    }

    /**
     * @param bool $custom
     */
    public function setCustom($custom)
    {
        $this->custom = $custom;
    }
}
