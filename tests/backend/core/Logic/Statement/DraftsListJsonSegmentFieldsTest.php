<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftsListJsonSegmentFields;
use PHPUnit\Framework\TestCase;

class DraftsListJsonSegmentFieldsTest extends TestCase
{
    private ?DraftsListJsonSegmentFields $sut = null;

    protected function setUp(): void
    {
        $this->sut = new DraftsListJsonSegmentFields();
    }

    // --- allSegmentsWrapped ---

    public function testAllSegmentsWrappedReturnsTrueWhenEverySegmentHasAMark(): void
    {
        // Arrange — two segments, two marks
        $data = $this->buildData(
            '<segment-mark data-segment-id="seg-1"><p>A.</p></segment-mark>'
            .'<segment-mark data-segment-id="seg-2"><p>B.</p></segment-mark>',
            [['id' => 'seg-1'], ['id' => 'seg-2']]
        );

        // Act & Assert
        self::assertTrue($this->sut->allSegmentsWrapped($data));
    }

    public function testAllSegmentsWrappedReturnsFalseWhenASegmentIsUnwrapped(): void
    {
        // Arrange — two segments but only one could be wrapped (e.g. text drifted / position out of bounds)
        $data = $this->buildData(
            '<segment-mark data-segment-id="seg-1"><p>A.</p></segment-mark><p>B.</p>',
            [['id' => 'seg-1'], ['id' => 'seg-2']]
        );

        // Act & Assert
        self::assertFalse($this->sut->allSegmentsWrapped($data));
    }

    public function testAllSegmentsWrappedReturnsFalseWhenNoSegmentIsWrapped(): void
    {
        // Arrange — plain textualReference, no marks at all
        $data = $this->buildData('<p>A.</p><p>B.</p>', [['id' => 'seg-1'], ['id' => 'seg-2']]);

        // Act & Assert
        self::assertFalse($this->sut->allSegmentsWrapped($data));
    }

    private function buildData(string $textualReference, array $segments): array
    {
        return [
            'data' => [
                'attributes' => [
                    'textualReference' => $textualReference,
                    'segments'         => $segments,
                ],
            ],
        ];
    }
}
