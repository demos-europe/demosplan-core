<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Entity\User;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanUserBundle\Repository\InstitutionTagRepository")
 */
class InstitutionTag extends CoreEntity implements UuidEntityInterface
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
    protected $title;

    /**
     * @var Collection<int,Orga>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", mappedBy="tags")
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(name="_o_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="_id", referencedColumnName="_o_id")}
     * )
     */
    protected $participationInstitutions;

    /**
     * @var Orga
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", nullable=false, onDelete="RESTRICT")
     */
    protected $owner;

    public function __construct()
    {
        $this->participationInstitutions = new ArrayCollection();
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
    public function getTaggedInstitutions(): Collection
    {
        return $this->participationInstitutions;
    }

    /**
     * @param Collection<int, Orga> $taggedInstitutions
     */
    public function setTaggedInstitutions(Collection $taggedInstitutions): void
    {
        $this->participationInstitutions = $taggedInstitutions;
    }
}
