<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Unit;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use PHPUnit\Framework\TestCase;

class SegmentOrderFieldsTest extends TestCase
{
    public function testSegmentCanBeEditLocked(): void
    {
        // Arrange
        $segment = new Segment();

        // Act
        $segment->setEditLocked(true);

        // Assert
        self::assertTrue($segment->isEditLocked());
    }

    public function testSegmentDefaultsToNotEditLocked(): void
    {
        // Arrange & Act
        $segment = new Segment();

        // Assert
        self::assertFalse($segment->isEditLocked());
    }

    public function testOrderInStatementSyncsWithOrderInProcedure(): void
    {
        // Arrange
        $segment = new Segment();

        // Act
        $segment->setOrderInStatement(5);

        // Assert - both fields should be in sync
        self::assertEquals(5, $segment->getOrderInStatement());
        self::assertEquals(5, $segment->getOrderInProcedure());
    }

    public function testOrderInProcedureSyncsWithOrderInStatement(): void
    {
        // Arrange
        $segment = new Segment();

        // Act
        $segment->setOrderInProcedure(3);

        // Assert - both fields should be in sync
        self::assertEquals(3, $segment->getOrderInProcedure());
        self::assertEquals(3, $segment->getOrderInStatement());
    }
}
