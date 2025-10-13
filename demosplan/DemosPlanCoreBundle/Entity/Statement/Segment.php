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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Segment entity - represents a structured part of a statement.
 *
 * Note: This entity implements SegmentInterface which requires getOrderInProcedure()/setOrderInProcedure()
 * methods. These are implemented as aliases to orderInStatement for backward compatibility with the interface
 * while using the unified order-based architecture internally.
 *
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
     * Legacy order field - kept for SegmentInterface compliance and backward compatibility.
     *
     * This field maintains the original segment ordering system and is required by
     * the SegmentInterface contract. During the transition period, it stays synchronized
     * with orderInStatement.
     *
     * @var int
     *
     * @ORM\Column(name="order_in_procedure", type="integer", nullable=true)
     */
    #[Assert\NotNull(groups: [SegmentInterface::VALIDATION_GROUP_SEGMENT_MANDATORY])]
    private $orderInProcedure;

    /**
     * Unified order field for content block composition (Segment + TextSection).
     *
     * This field implements the new unified ordering architecture where Segments and
     * TextSections share a single ordering sequence within a Statement. This enables
     * proper interleaving of structured (Segment) and unstructured (TextSection) content.
     *
     * @var int
     *
     * @ORM\Column(name="order_in_statement", type="integer", nullable=true)
     */
    #[Assert\NotNull(groups: [SegmentInterface::VALIDATION_GROUP_SEGMENT_MANDATORY])]
    private $orderInStatement;

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

    /**
     * @var CustomFieldValuesList
     *
     * @ORM\Column(type="dplan.custom_fields_value", nullable=true)
     */
    private $customFields;

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_edit_locked", type="boolean", nullable=false, options={"default":false})
     */
    protected $editLocked = false;

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

    /**
     * Get legacy order field (required by SegmentInterface).
     *
     * @return int The order in procedure
     */
    public function getOrderInProcedure(): int
    {
        return $this->orderInProcedure;
    }

    /**
     * Set legacy order field (required by SegmentInterface).
     *
     * During transition period, this synchronizes with orderInStatement
     * to maintain consistency between old and new architecture.
     *
     * @param int $orderInProcedure The order in procedure
     */
    public function setOrderInProcedure(int $orderInProcedure): void
    {
        $this->orderInProcedure = $orderInProcedure;
        // Keep both fields synchronized during transition
        $this->orderInStatement = $orderInProcedure;
    }

    /**
     * Get unified order field for new architecture.
     *
     * This method returns the order within the unified content block sequence
     * that includes both Segments and TextSections.
     *
     * @return int The order in statement
     */
    public function getOrderInStatement(): int
    {
        return $this->orderInStatement;
    }

    /**
     * Set unified order field for new architecture.
     *
     * During transition period, this synchronizes with orderInProcedure
     * to maintain consistency between old and new architecture.
     *
     * @param int $orderInStatement The order in statement
     */
    public function setOrderInStatement(int $orderInStatement): void
    {
        $this->orderInStatement = $orderInStatement;
        // Keep both fields synchronized during transition
        $this->orderInProcedure = $orderInStatement;
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

    public function getCustomFields(): ?CustomFieldValuesList
    {
        return $this->customFields;
    }

    public function setCustomFields($customFields): void
    {
        $this->customFields = $customFields;
    }

    public function isEditLocked(): bool
    {
        return $this->editLocked;
    }

    public function setEditLocked(bool $editLocked): void
    {
        $this->editLocked = $editLocked;
    }
}
