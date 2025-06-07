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
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\GlobalContentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\News\NewsHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * GlobalContent (derzeit GlobalFaq und GlobalNews).
 *
 * @ORM\Table(name="_platform_content")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ContentRepository")
 */
class GlobalContent extends CoreEntity implements UuidEntityInterface, GlobalContentInterface
{
    final public const NEW_GLOBAL_NEWS_VALIDATION_GROUP = 'new_global_news';
    final public const TYPE_NEWS = 'news';
    final public const PROCEDURE_ID_GLOBAL = 'global';
    final public const CONTEXT_GLOBAL_NEWS = 'global:news';
    final public const NAMESPACE_NEWS = 'content:news';

    /**
     * @var string|null
     *
     * @ORM\Column(name="_pc_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_type", type="string", length=60, nullable=false, options={"default":""})
     *
     * @deprecated No longer required. Previously, FAQ and GlobalNews were stored in this table together and this string
     *             identified their type ("faq" or "news"). Now, FAQ were moved to their own tables, so this string
     *             may only have the value "news" (or something is broken) and may thus be removed. The only reason
     *             it's still here is that there were no time resources for safely deleting this everywhere.
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_title", type="string", length=255, nullable=false, options={"default":""})
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP], message: 'error.mandatoryfield.heading')]
    protected $title = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_description", type="text", length=65535, nullable=true)
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP], message: 'error.mandatoryfield.teaser')]
    #[Assert\Type('string', groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP])]
    #[Assert\Length(max: NewsHandler::NEWS_DESCRIPTION_MAX_LENGTH, maxMessage: 'error.news.description.toolong', groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP])]
    protected $description = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_text", type="text", length=65535, nullable=true)
     */
    #[Assert\Type('string', groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP])]
    #[Assert\Length(max: NewsHandler::NEWS_TEXT_MAX_LENGTH, maxMessage: 'error.news.text.toolong', groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP])]
    protected $text = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_picture", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $picture = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_picture_title", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $pictitle = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_pdf", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $pdf = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_pc_pdf_title", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $pdftitle = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_pc_enabled", type="boolean", nullable=false, options={"default":false })
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP], message: 'error.mandatoryfield.status')]
    protected $enabled = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_pc_deleted", type="boolean", nullable=false, options={"default":false })
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pc_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_pc_modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pc_delete_date", type="datetime", nullable=false)
     */
    protected $deleteDate;

    /**
     * @var Collection<int, Role>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinTable(
     *     name="_platform_content_roles",
     *     joinColumns={@ORM\JoinColumn(name="_pc_id", referencedColumnName="_pc_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_r_id", referencedColumnName="_r_id", onDelete="CASCADE")}
     * )
     */
    #[Assert\Count(min: 1, groups: [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP], minMessage: 'error.mandatoryfield.visibility')]
    protected $roles;

    // todo: why is this a n:m relation?
    /**
     * @var Collection<int, Category>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Category", inversedBy ="globalContents")
     *
     * @ORM\JoinTable(
     *     name="_platform_content_categories",
     *     joinColumns={@ORM\JoinColumn(name="_pc_id", referencedColumnName="_pc_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_c_id", referencedColumnName="_c_id", onDelete="CASCADE")}
     * )
     */
    protected $categories;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="_c_id", onDelete="CASCADE", nullable=false)
     */
    protected CustomerInterface $customer;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="picture_id", referencedColumnName="_f_ident", onDelete="CASCADE", nullable=true)
     */
    protected ?File $pictureFile;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="pdf_id", referencedColumnName="_f_ident", onDelete="CASCADE", nullable=true)
     */
    protected ?File $pdfFile;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link GlobalContent::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return GlobalContent
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return GlobalContent
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
     * @return GlobalContent
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
     * Set text.
     *
     * @param string $text
     *
     * @return GlobalContent
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set picture.
     *
     * @param string $picture
     *
     * @return GlobalContent
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
     * @return GlobalContent
     */
    public function setPictitle($pictitle)
    {
        $this->pictitle = $pictitle;

        return $this;
    }

    /**
     * Get pictitle.
     *
     * @return string
     */
    public function getPictitle()
    {
        return $this->pictitle;
    }

    /**
     * Set pdf.
     *
     * @param string $pdf
     *
     * @return GlobalContent
     */
    public function setPdf($pdf)
    {
        $this->pdf = $pdf;

        return $this;
    }

    /**
     * Get pdf.
     *
     * @return string
     */
    public function getPdf()
    {
        return $this->pdf;
    }

    /**
     * Set pdftitle.
     *
     * @param string $pdftitle
     *
     * @return GlobalContent
     */
    public function setPdftitle($pdftitle)
    {
        $this->pdftitle = $pdftitle;

        return $this;
    }

    /**
     * Get pdftitle.
     *
     * @return string
     */
    public function getPdftitle()
    {
        return $this->pdftitle;
    }

    /**
     * Set enabled.
     *
     * @param bool $enabled
     *
     * @return GlobalContent
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (int) $enabled;

        return $this;
    }

    /**
     * Get enabled.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return (bool) $this->enabled;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return GlobalContent
     */
    public function setDeleted($deleted)
    {
        $this->deleted = (int) $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return (bool) $this->deleted;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return GlobalContent
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
     * @return GlobalContent
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
     * @return GlobalContent
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
     * Set Roles.
     *
     * @param array $roles
     *
     * @return GlobalContent
     */
    public function setRoles($roles)
    {
        $this->roles = new ArrayCollection($roles);

        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getRolesCollection(): Collection
    {
        return $this->roles;
    }

    /**
     * @param Collection<int, Role> $roles
     */
    public function setRolesCollection(Collection $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Add Role.
     *
     * @return GlobalContent
     */
    public function addRole(Role $role)
    {
        $this->roles->add($role);

        return $this;
    }

    /**
     * Get Roles.
     *
     * @return ArrayCollection
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Set Categories.
     *
     * @param array $categories
     *
     * @return GlobalContent
     */
    public function setCategories($categories)
    {
        $this->categories = new ArrayCollection($categories);

        return $this;
    }

    /**
     * Get Categories.
     *
     * @return ArrayCollection
     */
    public function getCategories()
    {
        return $this->categories;
    }

    public function setPictureFile($pictureFile): void
    {
        $this->pictureFile = $pictureFile;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategoriesCollection(): Collection
    {
        return $this->categories;
    }

    /**
     * @param Collection<int, Category> $categories
     */
    public function setCategoriesCollection(Collection $categories): self
    {
        $this->categories = $categories;

        return $this;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }
}
