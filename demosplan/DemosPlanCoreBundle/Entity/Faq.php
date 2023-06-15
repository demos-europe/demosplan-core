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
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\InvalidParameterTypeException;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqCategoryInterface;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * GlobalContent (derzeit GlobalFaq und GlobalNews).
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\FaqRepository")
 */
class Faq extends CoreEntity implements FaqInterface
{
    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected ?string $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, options={"default":""})
     */
    protected string $title = '';

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected string $text = '';

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default":false })
     */
    protected bool $enabled = false;

    /**
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected DateTime $createDate;

    /**
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected DateTime $modifyDate;

    /**
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(name="faq_id", referencedColumnName="id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="role_id", referencedColumnName="_r_id", onDelete="CASCADE")}
     * )
     */
    protected Collection $roles;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\FaqCategory")
     *
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    protected FaqCategory $faqCategory;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setCreateDate(DateTime $createDate): self
    {
        $this->createDate = $createDate;

        return $this;
    }

    public function getCreateDate(): DateTime
    {
        return $this->createDate;
    }

    public function setModifyDate(DateTime $modifyDate): self
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    public function getModifyDate(): DateTime
    {
        return $this->modifyDate;
    }

    /**
     * @param array<int, Role> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = new ArrayCollection($roles);

        return $this;
    }

    /**
     * Add Role.
     */
    public function addRole(Role $role): self
    {
        $this->roles->add($role);

        return $this;
    }

    /**
     * Get Roles.
     *
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function setCategory(FaqCategoryInterface $faqCategory): self
    {
        if (!$faqCategory instanceof FaqCategory) {
            throw new InvalidParameterTypeException('parameter must be of type: '.self::class.', '.$faqCategory::class.' given');
        }
        $this->faqCategory = $faqCategory;

        return $this;
    }

    /**
     * Get Category.
     */
    public function getCategory(): FaqCategory
    {
        return $this->faqCategory;
    }

    public function hasRoleGroupCode(string $code): bool
    {
        return $this->roles->exists(static function (int $index, Role $role) use ($code): bool {
            return $role->getGroupCode() === $code;
        });
    }
}
