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
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqCategoryInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use UnexpectedValueException;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\FaqCategoryRepository")
 */
class FaqCategory extends CoreEntity implements FaqCategoryInterface
{
    /**
     * These are allowed types, independent of the role.
     */
    public const FAQ_CATEGORY_TYPES_MANDATORY = [
        'system',
        'technische_voraussetzung',
        'bedienung',
        'oeb_bauleitplanung',
        'oeb_bob',
    ];

    /**
     * These are role-dependent types.
     */
    public const FAQ_CATEGORY_TYPES_OPTIONAL = 'custom_category';

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
    protected string $title;

    /**
     * Has no function for custom categories.
     *
     * @ORM\Column(type="string", length=50, nullable=false, options={"default":"custom_category"})
     */
    protected string $type = self::FAQ_CATEGORY_TYPES_OPTIONAL;

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
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(referencedColumnName="_c_id", onDelete="CASCADE", nullable=false)
     */
    protected Customer $customer;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        if (!in_array($type, self::FAQ_CATEGORY_TYPES_MANDATORY, true)
            && (self::FAQ_CATEGORY_TYPES_OPTIONAL !== $type)
        ) {
            throw new UnexpectedValueException(sprintf('FAQ category type has the value %s, please register this value in the entity.', $type));
        }

        $this->type = $type;
    }

    public function isCustom(): bool
    {
        return self::FAQ_CATEGORY_TYPES_OPTIONAL === $this->type;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
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
}
