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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\InstitutionTagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository")
 */
class InstitutionTag extends CoreEntity implements UuidEntityInterface, InstitutionTagInterface
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
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    #[Assert\NotNull(message: 'institutionTag.label.not.null')]
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    protected $label;

    /**
     * Institutions which were tagged with this tag (by the owner of this tag).
     *
     * @var Collection<int, OrgaInterface>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", mappedBy="assignedTags")
     */
    protected $taggedInstitutions;


    /**
     * Category to which this tag belongs.
     *
     * @var InstitutionTagCategory
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory", inversedBy="tags", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    protected $category;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    public function __construct(string $label)
    {
        $this->label = $label;
        $this->taggedInstitutions = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOwningOrganisation(): Orga
    {
        //@todo remove: adjust InstitutionTagInterface
        return $this->getTaggedInstitutions()->get(0);
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
}
