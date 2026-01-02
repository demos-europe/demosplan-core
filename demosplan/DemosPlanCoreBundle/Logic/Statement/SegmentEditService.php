<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\Exception\EditLockedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Service for editing segment content and structure.
 *
 * Handles:
 * - Text editing (always allowed, even after assessment starts)
 * - Structure editing (merge, split, delete - only before editLocked)
 * - Conversion between Segment and TextSection
 * - Reordering operations
 */
class SegmentEditService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OrderManagementService $orderManagementService,
    ) {
    }

    /**
     * Update the text content of a segment.
     *
     * This operation is always allowed, even after a segment enters assessment workflow.
     *
     * @param Segment $segment The segment to update
     * @param string $newText The new HTML content
     *
     * @return Segment The updated segment
     */
    public function updateSegmentText(
        Segment $segment,
        string $newText
    ): Segment {
        $oldText = $segment->getText();

        // Update text
        $segment->setText($newText);

        $this->entityManager->flush();

        // Fire event for recomputation/indexing
        // TODO: Implement SegmentTextChangedEvent and dispatch it
        // $this->eventDispatcher->dispatch(
        //     new SegmentTextChangedEvent($segment, $oldText, $newText)
        // );

        return $segment;
    }

    /**
     * Merge two segments into one.
     *
     * Creates a new segment with combined content and metadata from the first segment.
     * Both segments must not be locked (not in assessment workflow).
     *
     * @param Segment $seg1 The first segment (metadata is inherited from this one)
     * @param Segment $seg2 The second segment
     *
     * @return Segment The merged segment
     *
     * @throws EditLockedException If either segment is locked
     */
    public function mergeSegments(Segment $seg1, Segment $seg2): Segment
    {
        if ($seg1->isEditLocked() || $seg2->isEditLocked()) {
            throw new EditLockedException('Cannot merge segments in assessment');
        }

        // Create merged segment with inherited base properties
        $merged = $this->createSegmentWithInheritedProperties($seg1);
        $merged->setText($seg1->getText() . $seg2->getText());

        // Inherit metadata from first segment
        $merged->setPlace($seg1->getPlace());
        foreach ($seg1->getTags() as $tag) {
            $merged->addTag($tag);
        }

        // Copy external ID if present
        if (null !== $seg1->getExternId()) {
            $merged->setExternId($seg1->getExternId());
        }

        // Remove old segments and add merged one
        $statement = $seg1->getParentStatement();
        $statement->removeSegment($seg1);
        $statement->removeSegment($seg2);
        $statement->addSegment($merged);

        $this->entityManager->persist($merged);
        $this->entityManager->remove($seg1);
        $this->entityManager->remove($seg2);
        $this->entityManager->flush();

        // Renumber remaining blocks
        $this->orderManagementService->renumberContentBlocks($statement);

        // Fire event for aggregation/indexing
        // TODO: Implement SegmentMergedEvent and dispatch it
        // $this->eventDispatcher->dispatch(
        //     new SegmentMergedEvent($merged, $seg1, $seg2)
        // );

        return $merged;
    }

    /**
     * Split a segment into two parts.
     *
     * Creates two new segments from the provided HTML parts.
     * The segment must not be locked (not in assessment workflow).
     *
     * @param Segment $segment The segment to split
     * @param string $firstPartHtml HTML content for the first part
     * @param string $secondPartHtml HTML content for the second part
     *
     * @return array{0: Segment, 1: Segment} Array with [first segment, second segment]
     *
     * @throws EditLockedException If the segment is locked
     */
    public function splitSegment(
        Segment $segment,
        string $firstPartHtml,
        string $secondPartHtml
    ): array {
        if ($segment->isEditLocked()) {
            throw new EditLockedException('Cannot split segments in assessment');
        }

        $statement = $segment->getParentStatement();
        $originalOrder = $segment->getOrderInStatement();

        // Create first part with inherited base properties
        $first = $this->createSegmentWithInheritedProperties($segment);
        $first->setText($firstPartHtml);
        $first->setPlace($segment->getPlace());

        // Create second part with inherited base properties
        $second = $this->createSegmentWithInheritedProperties($segment);
        $second->setOrderInStatement($originalOrder + 1);
        $second->setText($secondPartHtml);
        $second->setPlace($segment->getPlace());

        // Remove original and add new segments
        $statement->removeSegment($segment);
        $statement->addSegment($first);
        $statement->addSegment($second);

        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->remove($segment);
        $this->entityManager->flush();

        // Renumber subsequent blocks
        $this->orderManagementService->renumberContentBlocks($statement);

        return [$first, $second];
    }

    /**
     * Delete a segment.
     *
     * The segment must not be locked (not in assessment workflow).
     *
     * @param Segment $segment The segment to delete
     *
     * @throws EditLockedException If the segment is locked
     */
    public function deleteSegment(Segment $segment): void
    {
        if ($segment->isEditLocked()) {
            throw new EditLockedException('Cannot delete segments in assessment');
        }

        $statement = $segment->getParentStatement();
        $statement->removeSegment($segment);

        $this->entityManager->remove($segment);
        $this->entityManager->flush();

        // Renumber remaining blocks
        $this->orderManagementService->renumberContentBlocks($statement);

        // Fire event for aggregation/indexing
        // TODO: Implement SegmentDeletedEvent and dispatch it
        // $this->eventDispatcher->dispatch(
        //     new SegmentDeletedEvent($segment)
        // );
    }

    /**
     * Convert a TextSection to a Segment.
     *
     * Creates a new segment with the same content and order.
     * Initializes with default place and no tags.
     *
     * @param TextSection $textSection The text section to convert
     *
     * @return Segment The new segment
     */
    public function convertTextSectionToSegment(TextSection $textSection): Segment
    {
        $statement = $textSection->getStatement();

        $segment = new Segment();
        $segment->setParentStatementOfSegment($statement);
        $segment->setOrderInStatement($textSection->getOrderInStatement());
        $segment->setText($textSection->getText());
        $segment->setProcedure($statement->getProcedure());
        $segment->setPhase($statement->getPhase());
        $segment->setPublicVerified($statement->getPublicVerified());

        // Initialize with default place
        // TODO: Implement getDefaultPlace() or inject PlaceRepository
        // $segment->setPlace($this->getDefaultPlace());

        $statement->removeTextSection($textSection);
        $statement->addSegment($segment);

        $this->entityManager->persist($segment);
        $this->entityManager->remove($textSection);
        $this->entityManager->flush();

        return $segment;
    }

    /**
     * Convert a Segment to a TextSection.
     *
     * Creates a new text section with the same content and order.
     * The segment must not be locked (not in assessment workflow).
     *
     * @param Segment $segment The segment to convert
     *
     * @return TextSection The new text section
     *
     * @throws EditLockedException If the segment is locked
     */
    public function convertSegmentToTextSection(Segment $segment): TextSection
    {
        if ($segment->isEditLocked()) {
            throw new EditLockedException('Cannot convert locked segments');
        }

        $statement = $segment->getParentStatement();

        $textSection = new TextSection();
        $textSection->setStatement($statement);
        $textSection->setOrderInStatement($segment->getOrderInStatement());
        $textSection->setText($segment->getText());
        $textSection->setTextRaw($segment->getText());

        $statement->removeSegment($segment);
        $statement->addTextSection($textSection);

        $this->entityManager->persist($textSection);
        $this->entityManager->remove($segment);
        $this->entityManager->flush();

        return $textSection;
    }

    /**
     * Move a segment to a different position.
     *
     * Updates the order of the segment and shifts other blocks as needed.
     * The segment must not be locked (not in assessment workflow).
     *
     * @param Segment $segment The segment to move
     * @param int $newOrder The new order position
     *
     * @throws EditLockedException If the segment is locked
     */
    public function moveSegment(Segment $segment, int $newOrder): void
    {
        if ($segment->isEditLocked()) {
            throw new EditLockedException('Cannot reorder locked segments');
        }

        $statement = $segment->getParentStatement();
        $oldOrder = $segment->getOrderInStatement();

        // Delegate to OrderManagementService
        $this->orderManagementService->moveBlock($statement, $oldOrder, $newOrder);

        // Fire event for cache invalidation
        // TODO: Implement ContentBlockReorderedEvent and dispatch it
        // $this->eventDispatcher->dispatch(
        //     new ContentBlockReorderedEvent($statement, $oldOrder, $newOrder)
        // );
    }

    /**
     * Create a new segment with inherited base properties from an existing segment.
     *
     * Copies all required fields and relationship properties from the source segment
     * to ensure the new segment is valid and consistent with the original.
     *
     * @param Segment $source The source segment to inherit properties from
     *
     * @return Segment A new segment with inherited base properties
     */
    private function createSegmentWithInheritedProperties(Segment $source): Segment
    {
        $newSegment = new Segment();
        $newSegment->setParentStatementOfSegment($source->getParentStatement());
        $newSegment->setOrderInStatement($source->getOrderInStatement());
        $newSegment->setProcedure($source->getProcedure());
        $newSegment->setPhase($source->getPhase());
        $newSegment->setPublicVerified($source->getPublicVerified());

        return $newSegment;
    }
}
