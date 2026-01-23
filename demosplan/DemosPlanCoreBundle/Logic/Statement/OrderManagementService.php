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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Service for managing the order of content blocks in statements.
 *
 * Handles:
 * - Renumbering content blocks to sequential order
 * - Moving blocks to different positions
 * - Inserting new blocks at specific positions
 */
class OrderManagementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Renumber all content blocks in a statement to sequential order (1, 2, 3, ...).
     *
     * This removes any gaps in the order sequence and ensures consistent ordering.
     * Useful after merge, split, or delete operations.
     *
     * @param Statement $statement The statement containing the blocks
     */
    public function renumberContentBlocks(Statement $statement): void
    {
        $blocks = $statement->getAllContentBlocks();

        $order = 1;
        foreach ($blocks as $block) {
            $block->setOrderInStatement($order++);
        }

        $this->entityManager->flush();
    }

    /**
     * Move a content block from one position to another.
     *
     * Shifts other blocks as needed to maintain sequential order.
     *
     * @param Statement $statement The statement containing the block
     * @param int       $fromOrder The current order position
     * @param int       $toOrder   The target order position
     */
    public function moveBlock(Statement $statement, int $fromOrder, int $toOrder): void
    {
        if ($fromOrder === $toOrder) {
            return; // No change needed
        }

        $blocks = $statement->getAllContentBlocks();

        // Find the block to move
        $blockToMove = null;
        foreach ($blocks as $block) {
            if ($block->getOrderInStatement() === $fromOrder) {
                $blockToMove = $block;
                break;
            }
        }

        if (null === $blockToMove) {
            throw new InvalidArgumentException(sprintf('No block found at order position %d', $fromOrder));
        }

        // Temporarily set to high number to avoid conflicts
        $blockToMove->setOrderInStatement(9999);
        $this->entityManager->flush();

        // Shift other blocks
        foreach ($blocks as $block) {
            $blockOrder = $block->getOrderInStatement();

            if ($fromOrder < $toOrder) {
                // Moving down: shift blocks up
                if ($blockOrder > $fromOrder && $blockOrder <= $toOrder) {
                    $block->setOrderInStatement($blockOrder - 1);
                }
            } else {
                // Moving up: shift blocks down
                if ($blockOrder >= $toOrder && $blockOrder < $fromOrder) {
                    $block->setOrderInStatement($blockOrder + 1);
                }
            }
        }

        // Set final position
        $blockToMove->setOrderInStatement($toOrder);

        $this->entityManager->flush();
    }

    /**
     * Insert a segment after an existing segment.
     *
     * Shifts subsequent blocks down to make room for the new segment.
     *
     * @param Segment $existing The existing segment after which to insert
     * @param Segment $new      The new segment to insert
     */
    public function insertSegmentAfter(Segment $existing, Segment $new): void
    {
        $statement = $existing->getParentStatement();
        $insertPosition = $existing->getOrderInStatement() + 1;

        // Shift subsequent blocks down
        $blocks = $statement->getAllContentBlocks();
        foreach ($blocks as $block) {
            if ($block->getOrderInStatement() >= $insertPosition) {
                $block->setOrderInStatement($block->getOrderInStatement() + 1);
            }
        }

        // Set position for new segment
        $new->setOrderInStatement($insertPosition);
        $new->setParentStatementOfSegment($statement);

        $statement->addSegment($new);

        $this->entityManager->persist($new);
        $this->entityManager->flush();
    }

    /**
     * Insert a text section between two positions.
     *
     * Shifts subsequent blocks down to make room for the new text section.
     *
     * @param int         $order1  The order position before the insertion point
     * @param int         $order2  The order position after the insertion point
     * @param TextSection $section The text section to insert
     */
    public function insertTextSectionBetween(int $order1, int $order2, TextSection $section): void
    {
        $statement = $section->getStatement();

        // Validate that order2 is immediately after order1
        if ($order2 !== $order1 + 1) {
            // There's a gap, insert at order1 + 1 and shift everything
            $insertPosition = $order1 + 1;

            $blocks = $statement->getAllContentBlocks();
            foreach ($blocks as $block) {
                if ($block->getOrderInStatement() >= $insertPosition) {
                    $block->setOrderInStatement($block->getOrderInStatement() + 1);
                }
            }

            $section->setOrderInStatement($insertPosition);
        } else {
            // order2 is immediately after order1, shift everything from order2 onwards
            $insertPosition = $order2;

            $blocks = $statement->getAllContentBlocks();
            foreach ($blocks as $block) {
                if ($block->getOrderInStatement() >= $insertPosition) {
                    $block->setOrderInStatement($block->getOrderInStatement() + 1);
                }
            }

            $section->setOrderInStatement($insertPosition);
        }

        $statement->addTextSection($section);

        $this->entityManager->persist($section);
        $this->entityManager->flush();
    }

    /**
     * Get the maximum order number currently in use for a statement.
     *
     * Returns 0 if there are no content blocks.
     *
     * @param Statement $statement The statement to check
     *
     * @return int The maximum order number, or 0 if no blocks exist
     */
    public function getMaxOrder(Statement $statement): int
    {
        $blocks = $statement->getAllContentBlocks();

        if (empty($blocks)) {
            return 0;
        }

        $maxOrder = 0;
        foreach ($blocks as $block) {
            $order = $block->getOrderInStatement();
            if ($order > $maxOrder) {
                $maxOrder = $order;
            }
        }

        return $maxOrder;
    }

    /**
     * Validate that the order sequence is correct (no duplicates, no missing numbers).
     *
     * Returns an array of errors, or empty array if valid.
     *
     * @param Statement $statement The statement to validate
     *
     * @return array<string> Array of error messages, empty if valid
     */
    public function validateOrderSequence(Statement $statement): array
    {
        $blocks = $statement->getAllContentBlocks();
        $errors = [];

        if (empty($blocks)) {
            return $errors; // Empty is valid
        }

        // Collect all order numbers
        $orders = [];
        foreach ($blocks as $block) {
            $order = $block->getOrderInStatement();
            if (isset($orders[$order])) {
                $errors[] = sprintf('Duplicate order number: %d', $order);
            }
            $orders[$order] = true;
        }

        // Check for gaps in sequence
        ksort($orders);
        $orderNumbers = array_keys($orders);
        $expectedOrder = 1;

        foreach ($orderNumbers as $actualOrder) {
            if ($actualOrder !== $expectedOrder) {
                $errors[] = sprintf('Gap in order sequence: expected %d, found %d', $expectedOrder, $actualOrder);
                break;
            }
            ++$expectedOrder;
        }

        return $errors;
    }
}
