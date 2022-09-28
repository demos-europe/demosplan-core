<?php declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table
 * @ORM\Entity(repositoryClass="OrgaInstitutionTagRepository")
 */
class OrgaInstitutionTag extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $label;

    /**
     * Institutions which were tagged with this tag (by the owner of this tag).
     *
     * @var Collection<int,Orga>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", mappedBy="tags")
     */
    protected $institutions;

    /**
     * Institution, which has created the tag and therefore is allowed to use, read, edit and delete it.
     *
     * @var Orga
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", cascade={"persist"})
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", nullable=false)
     */
    protected $owner;

    /**
     * @var DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTime
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    public function __construct(string $title, Orga $owner)
    {
        $this->label = $title;
        $this->owner = $owner;
        $this->institutions = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOwner(): Orga
    {
        return $this->owner;
    }

    public function setOwner(Orga $owner): void
    {
        $this->owner = $owner;
    }

    /**
     * @return Collection<int, Orga>
     */
    public function getInstitutions(): Collection
    {
        return $this->institutions;
    }

    /**
     * @param Collection<int, Orga> $institutions
     */
    public function setInstitutions(Collection $institutions): void
    {
        $this->institutions = $institutions;
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
    public function addInstitution(Orga $institution): bool
    {
        if (!$this->institutions->contains($institution)) {
            $this->institutions->add($institution);
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
