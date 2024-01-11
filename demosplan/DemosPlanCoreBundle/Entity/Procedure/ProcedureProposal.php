<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureProposalInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Procedure proposal.
 *
 * @ORM\Table
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedureProposalRepository")
 */
class ProcedureProposal extends CoreEntity implements UuidEntityInterface, ProcedureProposalInterface
{
    final public const STATUS = [
        'new'                                 => 'new',
        'has_been_transformed_into_procedure' => 'has_been_transformed_into_procedure',
    ];

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
     * @ORM\Column(type="string", length=4096, nullable=false)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    protected $description = '';

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    protected $additionalExplanation = '';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=2048, nullable=false)
     */
    protected $coordinate = '';

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="user", referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     */
    protected $user;

    /**
     * May only have one of the status listed in self::STATUS.
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=false, options={"default":"new"})
     */
    protected $status = 'new';

    /**
     * @var Collection<int, File>
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File",)
     *
     * @ORM\JoinTable(
     *     name="procedureproposal_file_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="procedureProposal", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="file", referencedColumnName="_f_ident")}
     * )
     */
    protected $files;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set name.
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }

    public function setCreatedDate(DateTime $createdDate): void
    {
        $this->createdDate = $createdDate;
    }

    public function getModifiedDate(): DateTime
    {
        return $this->modifiedDate;
    }

    public function setModifiedDate(DateTime $modifiedDate): void
    {
        $this->modifiedDate = $modifiedDate;
    }

    public function getCoordinate(): string
    {
        return $this->coordinate;
    }

    public function setCoordinate(string $coordinate): void
    {
        $this->coordinate = $coordinate;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getFiles(): ArrayCollection
    {
        return $this->files;
    }

    public function setFiles(ArrayCollection $files): void
    {
        $this->files = $files;
    }

    public function addFile(File $file): void
    {
        $this->files->add($file);
    }

    public function removeFile(File $file): void
    {
        $this->files->remove($file);
    }

    public function getAdditionalExplanation(): string
    {
        return $this->additionalExplanation;
    }

    public function setAdditionalExplanation(string $additionalExplanation): void
    {
        $this->additionalExplanation = $additionalExplanation;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
}
