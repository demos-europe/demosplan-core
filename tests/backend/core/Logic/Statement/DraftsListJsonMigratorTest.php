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

use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftsListJsonMigrator;
use PHPUnit\Framework\TestCase;

class DraftsListJsonMigratorTest extends TestCase
{
    private ?DraftsListJsonMigrator $sut = null;

    protected function setUp(): void
    {
        $this->sut = new DraftsListJsonMigrator();
    }

    // --- needsMigration ---

    public function testNeedsMigrationReturnsTrueForOldFormat(): void
    {
        // Arrange
        $data = $this->buildData(
            '<p>Sky is blue.</p><p>Grass is green.</p>',
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5, 'text' => '<p>Sky is blue.</p>'],
                ['id' => 'seg-2', 'charStart' => 6, 'charEnd' => 10, 'text' => '<p>Grass is green.</p>'],
            ]
        );

        // Act & Assert
        self::assertTrue($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseWhenSegmentMarkAlreadyPresent(): void
    {
        // Arrange
        $data = $this->buildData(
            '<segment-mark data-segment-id="seg-1"><p>Sky is blue.</p></segment-mark>',
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5, 'text' => '<p>Sky is blue.</p>'],
            ]
        );

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseForEmptySegments(): void
    {
        // Arrange
        $data = $this->buildData('<p>Some text.</p>', []);

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseWhenNoCharStartField(): void
    {
        // Arrange — new format: segments have no charStart
        $data = $this->buildData(
            '<segment-mark data-segment-id="seg-1"><p>Sky is blue.</p></segment-mark>',
            [
                ['id' => 'seg-1', 'text' => '<p>Sky is blue.</p>'],
            ]
        );

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    // --- migrate: example 1 — plain paragraphs ---

    public function testMigrateWrapsEachSegmentTextWithSegmentMark(): void
    {
        // Arrange
        $textA = '<p>Sky is blue.</p>';
        $textB = '<p>Grass is green.</p>';
        $data = $this->buildData(
            $textA.$textB,
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5, 'text' => $textA],
                ['id' => 'seg-2', 'charStart' => 6, 'charEnd' => 10, 'text' => $textB],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert
        self::assertSame(
            '<segment-mark data-segment-id="seg-1">'.$textA.'</segment-mark>'
            .'<segment-mark data-segment-id="seg-2">'.$textB.'</segment-mark>',
            $ref
        );
    }

    public function testMigrateRemovesPositionFieldsFromAllSegments(): void
    {
        // Arrange
        $data = $this->buildData(
            '<p>Sky is blue.</p>',
            [
                [
                    'id'                  => 'seg-1',
                    'charStart'           => 0,
                    'charEnd'             => 5,
                    'charStartInit'       => 0,
                    'charEndInit'         => 5,
                    'hasProsemirrorIndex' => true,
                    'text'                => '<p>Sky is blue.</p>',
                ],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $segment = $result['data']['attributes']['segments'][0];

        // Assert
        self::assertArrayNotHasKey('charStart', $segment);
        self::assertArrayNotHasKey('charEnd', $segment);
        self::assertArrayNotHasKey('charStartInit', $segment);
        self::assertArrayNotHasKey('charEndInit', $segment);
        self::assertArrayNotHasKey('hasProsemirrorIndex', $segment);
        self::assertSame('seg-1', $segment['id']);
    }

    public function testMigrateSortsSegmentsByCharStartBeforeProcessing(): void
    {
        // Arrange — segments are intentionally out of document order
        $textA = '<p>First sentence.</p>';
        $textB = '<p>Second sentence.</p>';
        $data = $this->buildData(
            $textA.$textB,
            [
                ['id' => 'seg-b', 'charStart' => 10, 'charEnd' => 15, 'text' => $textB],
                ['id' => 'seg-a', 'charStart' => 0,  'charEnd' => 5,  'text' => $textA],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert — both marks must be present; ordering them correctly is what prevents
        // substr_replace from shifting offsets and breaking the second lookup
        self::assertStringContainsString('<segment-mark data-segment-id="seg-a">'.$textA.'</segment-mark>', $ref);
        self::assertStringContainsString('<segment-mark data-segment-id="seg-b">'.$textB.'</segment-mark>', $ref);
    }

    // --- migrate: example 2 — segment text contains a list ---

    public function testMigrateHandlesListInSegmentText(): void
    {
        // Arrange
        $textA = '<p>Intro paragraph.</p>';
        $textB = '<ol><li>First item.</li><li>Second item.</li></ol>';
        $textC = '<p>Closing paragraph.</p>';
        $data = $this->buildData(
            $textA.$textB.$textC,
            [
                ['id' => 'seg-a', 'charStart' => 0, 'charEnd' => 3, 'text' => $textA],
                ['id' => 'seg-b', 'charStart' => 4, 'charEnd' => 7, 'text' => $textB],
                ['id' => 'seg-c', 'charStart' => 8, 'charEnd' => 11, 'text' => $textC],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert
        self::assertStringContainsString('<segment-mark data-segment-id="seg-a">'.$textA.'</segment-mark>', $ref);
        self::assertStringContainsString('<segment-mark data-segment-id="seg-b">'.$textB.'</segment-mark>', $ref);
        self::assertStringContainsString('<segment-mark data-segment-id="seg-c">'.$textC.'</segment-mark>', $ref);
    }

    public function testMigrateHandlesDuplicateSegmentText(): void
    {
        // Arrange — two segments share identical boilerplate text (e.g. two <p>&nbsp;</p> spacers).
        // Without a running offset, strpos always resolves to the first occurrence and wraps it twice,
        // producing nested <segment-mark> tags with a wrong id on the second segment.
        $spacer = '<p>&nbsp;</p>';
        $data = $this->buildData(
            $spacer.$spacer,
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 2, 'text' => $spacer],
                ['id' => 'seg-2', 'charStart' => 3, 'charEnd' => 5, 'text' => $spacer],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert — each spacer gets its own distinct mark; no nesting
        self::assertSame(
            '<segment-mark data-segment-id="seg-1">'.$spacer.'</segment-mark>'
            .'<segment-mark data-segment-id="seg-2">'.$spacer.'</segment-mark>',
            $ref
        );
    }

    public function testMigrateHandlesSubstringSegmentText(): void
    {
        // Arrange — seg-2 text is a substring of seg-1 text.
        // Without a running offset, the second strpos call finds the substring inside the already-wrapped
        // seg-1 region and produces a nested <segment-mark> inside seg-1's wrapper.
        $short = '<p>Blue.</p>';
        $long  = '<p>Blue.</p><p>Sky is blue.</p>';
        $data = $this->buildData(
            $long.$short,
            [
                ['id' => 'seg-1', 'charStart' => 0,  'charEnd' => 10, 'text' => $long],
                ['id' => 'seg-2', 'charStart' => 11, 'charEnd' => 15, 'text' => $short],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert — seg-2 is wrapped at its own occurrence after seg-1, not nested inside seg-1
        self::assertSame(
            '<segment-mark data-segment-id="seg-1">'.$long.'</segment-mark>'
            .'<segment-mark data-segment-id="seg-2">'.$short.'</segment-mark>',
            $ref
        );
    }

    public function testMigrateSkipsSegmentWhenTextNotFoundInTextualReference(): void
    {
        // Arrange — seg-b text does not appear in textualReference
        $existingText = '<p>Only this exists.</p>';
        $data = $this->buildData(
            $existingText,
            [
                ['id' => 'seg-a', 'charStart' => 0, 'charEnd' => 5, 'text' => $existingText],
                ['id' => 'seg-b', 'charStart' => 6, 'charEnd' => 10, 'text' => '<p>This is missing.</p>'],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert
        self::assertStringContainsString('<segment-mark data-segment-id="seg-a">', $ref);
        self::assertStringNotContainsString('<segment-mark data-segment-id="seg-b">', $ref);
    }

    // --- helpers ---

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
