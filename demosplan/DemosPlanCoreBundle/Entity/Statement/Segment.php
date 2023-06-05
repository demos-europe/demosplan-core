<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SegmentRepository")
 */
class Segment extends Statement implements SegmentInterface
{
    public const VALIDATION_GROUP_SEGMENT_MANDATORY = 'segment_mandatory';
    public const VALIDATION_GROUP_DEFAULT = 'segment_default';
    public const VALIDATION_GROUP_IMPORT = 'segment_import';

    public const RECOMMENDATION_FIELD_NAME = 'recommendation';

    /**
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="segmentsOfStatement", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="segment_statement_fk", referencedColumnName="_st_id", nullable=true)
     */
    #[Assert\NotNull(groups: [Segment::VALIDATION_GROUP_IMPORT])]
    #[Assert\Type(groups: [Segment::VALIDATION_GROUP_IMPORT], type: 'demosplan\DemosPlanCoreBundle\Entity\Statement\Statement')]
    protected $parentStatementOfSegment;

    /**
     * @var Collection<int, SegmentComment>
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
    #[Assert\NotNull(groups: [Segment::VALIDATION_GROUP_SEGMENT_MANDATORY])]
    private $orderInProcedure;

    /**
     * The {@link Place} this instance is coupled to.
     *
     * Already replaces {@link Statement::$status} for {@link Segment} instances, meaning
     * {@link Statement::$status} is to be ignored in the context of a {@link Segment}!
     * Later {@link Statement::$status} will be fully replaced by a relationship.
     *
     * Because {@link Segment} and {@link Statement} share a single table (and {@link Statement}s
     * do not yet have a {@link Place}) we set the place
     * to `nullable=true`, even though it must never be `null` for a {@link Segment}.
     *
     * @var Place
     *
     * @ORM\ManyToOne(targetEntity=Place::class)
     *
     * @ORM\JoinColumn(referencedColumnName="id", nullable=true)
     */
    #[Assert\NotBlank(groups: ['Default', Segment::VALIDATION_GROUP_IMPORT])]
    private $place;

    public function __construct()
    {
        parent::__construct();
        $this->comments = new ArrayCollection();
    }

    public function getParentStatementOfSegment(): Statement
    {
        return $this->parentStatementOfSegment;
    }

    public function getParentStatement(): Statement
    {
        return $this->parentStatementOfSegment;
    }

    public function setParentStatementOfSegment(Statement $parentStatementOfSegment): void
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

    public function setPlace(Place $place): self
    {
        $this->place = $place;

        return $this;
    }

    public function getPlace(): Place
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

    public function addComment(SegmentComment $comment): self
    {
        $this->comments->add($comment);

        return $this;
    }
}
