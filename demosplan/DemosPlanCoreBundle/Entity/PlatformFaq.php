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
use DemosEurope\DemosplanAddon\Contracts\Entities\FaqInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity()
 */
class PlatformFaq extends CoreEntity implements FaqInterface
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
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"default":""})
     */
    protected $title = '';

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $text = '';

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":false })
     */
    protected $enabled = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var Collection<int, Role>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Role")
     *
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(name="platformFaq_id", referencedColumnName="id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="role_id", referencedColumnName="_r_id", onDelete="CASCADE")}
     * )
     */
    protected $roles;

    /**
     * @var PlatformFaqCategory
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\PlatformFaqCategory")
     *
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    protected $platformFaqCategory;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set title.
     *
     * @param string $title
     */
    public function setTitle($title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set text.
     *
     * @param string $text
     */
    public function setText($text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Set enabled.
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled): self
    {
        $this->enabled = (int) $enabled;

        return $this;
    }

    /**
     * Get enabled.
     */
    public function getEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     */
    public function setCreateDate($createDate): self
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     */
    public function getCreateDate(): DateTime
    {
        return $this->createDate;
    }

    /**
     * Set modifyDate.
     *
     * @param DateTime $modifyDate
     */
    public function setModifyDate($modifyDate): self
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    /**
     * Get modifyDate.
     */
    public function getModifyDate(): DateTime
    {
        return $this->modifyDate;
    }

    /**
     * Set Roles.
     *
     * @param array $roles
     */
    public function setRoles($roles): self
    {
        $this->roles = new ArrayCollection($roles);

        return $this;
    }

    /**
     * Add Role.
     */
    public function addRole(RoleInterface $role): self
    {
        $this->roles->add($role);

        return $this;
    }

    /**
     * Get Roles.
     *
     * @return Collection<int, RoleInterface>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /**
     * Set Category.
     *
     * @param PlatformFaqCategory $platformFaqCategory
     */
    public function setCategory($platformFaqCategory): self
    {
        $this->platformFaqCategory = $platformFaqCategory;

        return $this;
    }

    /**
     * Get Category.
     */
    public function getCategory(): PlatformFaqCategory
    {
        return $this->platformFaqCategory;
    }

    public function hasRoleGroupCode(string $code): bool
    {
        return $this->roles->exists(static fn (int $index, Role $role): bool => $role->getGroupCode() === $code);
    }
}
