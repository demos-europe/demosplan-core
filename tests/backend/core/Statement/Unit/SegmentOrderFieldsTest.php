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
    public function testSegmentCanHaveExternalId(): void
    {
        // Arrange
        $segment = new Segment();

        // Act
        $segment->setExternId('AI-SEG-001');

        // Assert
        self::assertEquals('AI-SEG-001', $segment->getExternId());
    }

    public function testSegmentExternalIdDefaultsToEmptyString(): void
    {
        // Arrange
        $segment = new Segment();

        // Act - don't set externId

        // Assert
        self::assertEquals('', $segment->getExternId());
    }

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
}
