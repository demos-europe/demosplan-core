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

use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\InstitutionTagCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\InstitutionTagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\UniqueConstraint(name: 'unique_label_for_category', columns: ['category_id', 'label'])]
#[ORM\Entity(repositoryClass: InstitutionTagRepository::class)]
class InstitutionTag extends CoreEntity implements UuidEntityInterface, InstitutionTagInterface
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

    /**
     * @var string
     */
    #[Assert\NotNull(message: 'institutionTag.label.not.null')]
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    protected $label;

    /**
     * Institutions which were tagged with this tag (by the owner of this tag).
     *
     * @var Collection<int, OrgaInterface>
     */
    #[ORM\ManyToMany(targetEntity: Orga::class, mappedBy: 'assignedTags')]
    protected $taggedInstitutions;

    /**
     * Category to which this tag belongs.
     *
     * @var InstitutionTagCategory
     *
     *
     */
    #[ORM\JoinColumn(referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: InstitutionTagCategory::class, inversedBy: 'tags', cascade: ['persist'])]
    protected $category;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    protected $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    private $modificationDate;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Orga>
     */
    public function getTaggedInstitutions(): Collection
    {
        return $this->taggedInstitutions;
    }

    /**
     * @param Collection<int, OrgaInterface> $institutions
     */
    public function setTaggedInstitutions(Collection $institutions): void
    {
        $this->taggedInstitutions = $institutions;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }

    /**
     * @return bool - true if the given statement was added to this tag, otherwise false
     */
    public function addTaggedInstitution(OrgaInterface $institution): bool
    {
        if (!$this->taggedInstitutions->contains($institution)) {
            $this->taggedInstitutions->add($institution);
            $institution->addAssignedTag($this);

            return true;
        }

        return false;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function setCategory(InstitutionTagCategoryInterface $category): void
    {
        $this->category = $category;
    }
}
