<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagCategoryRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\InstitutionTagCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\UniqueConstraint(name: 'unique_category_name_for_customer', columns: ['customer_id', 'name'])]
#[ORM\Entity(repositoryClass: InstitutionTagCategoryRepository::class)]
class InstitutionTagCategory extends CoreEntity implements UuidEntityInterface, InstitutionTagCategoryInterface
{
    /**
     * @var string|null
     *
     *
     *
     *
     */
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected $id;

    #[Assert\NotNull(message: 'institutionTag.label.not.null')]
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    protected string $name;

    #[ORM\JoinColumn(referencedColumnName: '_c_id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'customerCategories', cascade: ['persist'])]
    protected Customer $customer;

    /**
     * @var Collection<int, InstitutionTag>
     */
    #[ORM\OneToMany(targetEntity: InstitutionTag::class, mappedBy: 'category', cascade: ['remove'])]
    protected $tags;

    /**
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    protected DateTime $creationDate;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    private DateTime $modificationDate;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setCustomer(CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }
}
