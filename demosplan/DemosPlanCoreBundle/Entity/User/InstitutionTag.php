<?php declare(strict_types=1);


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
        $this->title = $title;
        $this->owner = $owner;
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
        if (!$this->participationInstitutions->contains($institution)) {
            $this->participationInstitutions->add($institution);
            return true;
        }

        return false;
    }
}
