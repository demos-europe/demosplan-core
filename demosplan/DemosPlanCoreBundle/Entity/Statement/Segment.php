<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentCommentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SegmentRepository")
 */
class Segment extends Statement implements SegmentInterface
{
    /**
     * @var StatementInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="segmentsOfStatement", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="segment_statement_fk", referencedColumnName="_st_id", nullable=true)
     */
    #[Assert\NotNull(groups: [SegmentInterface::VALIDATION_GROUP_IMPORT])]
    #[Assert\Type(groups: [SegmentInterface::VALIDATION_GROUP_IMPORT], type: 'demosplan\DemosPlanCoreBundle\Entity\Statement\Statement')]
    protected $parentStatementOfSegment;

    /**
     * @var Collection<int, SegmentCommentInterface>
     *
     * @ORM\OneToMany(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\SegmentComment",
     *     mappedBy="segment",
     *     orphanRemoval=true,
     *     cascade={"remove"}
     * )
     */
    protected $comments;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    #[Assert\NotNull(groups: [SegmentInterface::VALIDATION_GROUP_SEGMENT_MANDATORY])]
    private $orderInProcedure;

    /**
     * The {@link PlaceInterface} this instance is coupled to.
     *
     * Already replaces {@link StatementInterface::$status} for {@link SegmentInterface} instances, meaning
     * {@link StatementInterface::$status} is to be ignored in the context of a {@link SegmentInterface}!
     * Later {@link StatementInterface::$status} will be fully replaced by a relationship.
     *
     * Because {@link SegmentInterface} and {@link StatementInterface} share a single table (and {@link StatementInterface}s
     * do not yet have a {@link PlaceInterface}) we set the place
     * to `nullable=true`, even though it must never be `null` for a {@link SegmentInterface}.
     *
     * @var PlaceInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Workflow\Place")
     *
     * @ORM\JoinColumn(name="place_id", referencedColumnName="id", nullable=true)
     */
    #[Assert\NotBlank(groups: [ResourceTypeService::VALIDATION_GROUP_DEFAULT, SegmentInterface::VALIDATION_GROUP_IMPORT])]
    private $place;

    public function __construct()
    {
        parent::__construct();
        $this->comments = new ArrayCollection();
    }

    public function getParentStatementOfSegment(): StatementInterface
    {
        return $this->parentStatementOfSegment;
    }

    public function getParentStatement(): StatementInterface
    {
        return $this->parentStatementOfSegment;
    }

    public function setParentStatementOfSegment(StatementInterface $parentStatementOfSegment): void
    {
        $this->parentStatementOfSegment = $parentStatementOfSegment;
    }

    /**
     * Tells us that this entity is not a segment.
     */
    public function isSegment(): bool
    {
        return true;
    }

    public function getOrderInProcedure(): int
    {
        return $this->orderInProcedure;
    }

    public function setOrderInProcedure(int $orderInProcedure): void
    {
        $this->orderInProcedure = $orderInProcedure;
    }

    public function setPlace(PlaceInterface $place): self
    {
        $this->place = $place;

        return $this;
    }

    public function getPlace(): PlaceInterface
    {
        return $this->place;
    }

    /**
     * Needed for elasticsearch indexing.
     */
    public function getPlaceId(): string
    {
        return $this->place->getId();
    }

    public function addComment(SegmentCommentInterface $comment): self
    {
        $this->comments->add($comment);

        return $this;
    }
}
