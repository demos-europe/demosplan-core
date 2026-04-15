<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use demosplan\DemosPlanCoreBundle\Repository\SegmentCommentRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentCommentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SegmentCommentRepository::class)]
class SegmentComment implements UuidEntityInterface, SegmentCommentInterface
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
     * @var SegmentInterface
     *
     *
     */
    #[Assert\NotNull]
    #[ORM\JoinColumn(referencedColumnName: '_st_id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Segment::class, inversedBy: 'comments')]
    protected $segment;

    /**
     * May be `null` if the {@link UserInterface} was deleted after this instance was created.
     *
     * @var UserInterface|null
     *
     *
     */
    #[ORM\JoinColumn(referencedColumnName: '_u_id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $submitter;

    /**
     * May be `null` if the {@link PlaceInterface} was deleted after this instance was created.
     *
     * @var PlaceInterface|null
     *
     *
     */
    #[ORM\JoinColumn(referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: Place::class)]
    protected $place;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    protected $creationDate;

    /**
     * Max length 2^16. Chosen to not limit users in any reasonable use case while still
     * preventing them from pasting complete books.
     *
     * @var string
     */
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 65536)]
    #[ORM\Column(type: 'text', nullable: false)]
    protected $text;

    public function __construct(Segment $segment, User $submitter, Place $place, string $text)
    {
        $this->segment = $segment;
        $this->submitter = $submitter;
        $this->place = $place;
        $this->text = $text;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSubmitter(): ?UserInterface
    {
        return $this->submitter;
    }

    public function getPlace(): ?PlaceInterface
    {
        return $this->place;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getSegment(): SegmentInterface
    {
        return $this->segment;
    }
}
