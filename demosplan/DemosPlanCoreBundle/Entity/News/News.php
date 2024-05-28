<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\News;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\NewsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\AllRolesInGroupPresentConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\DateInFutureConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\News\NewsHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="_news")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\NewsRepository")
 */
class News extends CoreEntity implements UuidEntityInterface, NewsInterface
{
    final public const MANUAL_SORT_NAMESPACE = 'news';
    final public const NEW_PROCEDURE_NEWS_VALIDATION_GROUP = 'newProcedureNews';

    /**
     * @var string|null
     *
     * @ORM\Column(name="_n_id", type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(name="_p_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    #[Assert\NotBlank(allowNull: false)]
    protected $pId;

    /**
     * @var string
     *
     * @ORM\Column(name="_n_title", type="string", length=255, nullable=false)
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, groups: [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP], message: 'error.mandatoryfield.heading')]
    protected $title = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_n_description", type="text", length=65535, nullable=false)
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, groups: [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP], message: 'error.mandatoryfield.teaser')]
    #[Assert\Type('string', groups: [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP])]
    #[Assert\Length(max: NewsHandler::NEWS_DESCRIPTION_MAX_LENGTH, maxMessage: 'error.news.description.toolong', groups: [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP])]
    protected $description = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_n_text", type="text", length=65535, nullable=false)
     */
    #[Assert\Type('string', groups: [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP])]
    #[Assert\Length(max: NewsHandler::NEWS_TEXT_MAX_LENGTH, maxMessage: 'error.news.text.toolong', groups: [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP])]
    protected $text = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_n_picture", type="string", length=255, nullable=false)
     */
    protected $picture = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_n_picture_title", type="string", length=255, nullable=false)
     */
    protected $pictitle = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_n_pdf", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $pdf = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_n_pdf_title", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $pdftitle = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_n_enabled", type="boolean", nullable=false)
     */
    #[Assert\NotBlank(normalizer: 'trim', allowNull: false, groups: [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP], message: 'error.mandatoryfield.status')]
    protected $enabled = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_n_deleted", type="boolean", nullable=false)
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_n_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_n_modify_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_n_delete_date", type="datetime", nullable=false)
     */
    protected $deleteDate;

    /**
     * @var Collection<int, Role>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinTable(
     *     name="_news_roles",
     *     joinColumns={@ORM\JoinColumn(name="_n_id", referencedColumnName="_n_id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_r_id", referencedColumnName="_r_id", onDelete="CASCADE")}
     * )
     *
     * @AllRolesInGroupPresentConstraint(groupCodes={Role::GLAUTH}, groups={News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP})
     */
    protected $roles;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(type = "datetime", nullable = true)
     *
     * @DateInFutureConstraint(groups={News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP})
     */
    protected $designatedSwitchDate;

    /**
     * @var bool
     *
     * @ORM\Column(type = "boolean", nullable = true)
     */
    protected $designatedState;

    /**
     * @var bool
     *
     * @ORM\Column(type = "boolean", nullable = false)
     */
    protected $determinedToSwitch = false;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    /**
     * Set Id.
     *
     * @param string $ident
     *
     * @return News
     */
    public function setIdent($ident)
    {
        $this->ident = $ident;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link News::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set Id.
     *
     * @param string $procedureId
     *
     * @return News
     */
    public function setPId($procedureId)
    {
        $this->pId = $procedureId;

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
     * Set Title.
     *
     * @param string $title
     *
     * @return News
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get Title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set Description.
     *
     * @param string $description
     *
     * @return News
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get Description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set Text.
     *
     * @param string $text
     *
     * @return News
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get Text.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set Picture.
     *
     * @param string $picture
     *
     * @return News
     */
    public function setPicture($picture)
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * Get Picture.
     *
     * @return string
     */
    public function getPicture()
    {
        return $this->picture;
    }

    /**
     * Set PictureTitle.
     *
     * @param string $pictitle
     *
     * @return News
     */
    public function setPictitle($pictitle)
    {
        $this->pictitle = $pictitle;

        return $this;
    }

    /**
     * Get PictureTitle.
     *
     * @return string
     */
    public function getPictitle()
    {
        return $this->pictitle;
    }

    /**
     * Set Pdf.
     *
     * @param string $pdf
     *
     * @return News
     */
    public function setPdf($pdf)
    {
        $this->pdf = $pdf;

        return $this;
    }

    /**
     * Get Pdf.
     *
     * @return string
     */
    public function getPdf()
    {
        return $this->pdf;
    }

    /**
     * Set PdfTitle.
     *
     * @param string $pdftitle
     *
     * @return News
     */
    public function setPdftitle($pdftitle)
    {
        $this->pdftitle = $pdftitle;

        return $this;
    }

    /**
     * Get Pdftitle.
     *
     * @return string
     */
    public function getPdftitle()
    {
        return $this->pdftitle;
    }

    /**
     * Set Enabled.
     */
    public function setEnabled(bool $enabled): News
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get Enabled.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return (bool) $this->enabled;
    }

    /**
     * Get Enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * Set Deleted.
     *
     * @param bool $deleted
     *
     * @return News
     */
    public function setDeleted($deleted)
    {
        $this->deleted = (int) $deleted;

        return $this;
    }

    /**
     * Get Deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return (bool) $this->deleted;
    }

    /**
     * Set CreateDate.
     *
     * @param DateTime $createDate
     *
     * @return News
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get CreateDate.
     *
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * Set ModifyDate.
     *
     * @param DateTime $modifyDate
     *
     * @return News
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    /**
     * Get nModifyDate.
     *
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * Set nDeleteDate.
     *
     * @param DateTime $deleteDate
     *
     * @return News
     */
    public function setDeleteDate($deleteDate)
    {
        $this->deleteDate = $deleteDate;

        return $this;
    }

    /**
     * Get DeleteDate.
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
     * @return News
     */
    public function setRoles($roles)
    {
        $this->roles = new ArrayCollection($roles);

        return $this;
    }

    public function setRolesCollection(Collection $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Add Role.
     *
     * @return News
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

    public function getDesignatedSwitchDate(): ?DateTime
    {
        return $this->designatedSwitchDate;
    }

    public function setDesignatedSwitchDate(DateTime $designatedSwitchDate = null): void
    {
        $this->designatedSwitchDate = $designatedSwitchDate;
    }

    public function isDeterminedToSwitch(): bool
    {
        return $this->determinedToSwitch;
    }

    public function setDeterminedToSwitch(bool $determinedToSwitch): void
    {
        $this->determinedToSwitch = $determinedToSwitch;
    }

    public function getDesignatedState(): ?bool
    {
        return $this->designatedState;
    }

    public function setDesignatedState(?bool $designatedState): void
    {
        $this->designatedState = $designatedState;
    }

    public function setProcedure(Procedure $procedure): self
    {
        $this->pId = $procedure->getId();

        return $this;
    }

    /**
     * Wandle das Objekt in ein Array um.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'ident'             		=> $this->ident,
            'pId'           			=> $this->pId,
            'title' 				    => $this->title,
            'description'       		=> $this->description,
            'text'             			=> $this->text,
            'picture'         			=> $this->picture,
            'pictitle'            		=> $this->pictitle,
            'pdf'            			=> $this->pdf,
            'pdftitle'              	=> $this->pdftitle,
            'enabled'           		=> $this->enabled,
            'deleted'       			=> $this->deleted,
            'createdDate'             	=> $this->createDate,
            'modifyDate'             	=> $this->modifyDate,
            'deleteDate'              	=> $this->deleteDate,
            'roles'               		=> $this->roles,
            'designatedSwitchDate'      => $this->designatedSwitchDate,
            'designatedState'           => $this->designatedState,
            'determinedToSwitch'       	=> $this->determinedToSwitch
        ];
    }
}
