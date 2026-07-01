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

use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftsListJsonPositionMigrator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftsListJsonSegmentFields;
use PHPUnit\Framework\TestCase;

class DraftsListJsonPositionMigratorTest extends TestCase
{
    private ?DraftsListJsonPositionMigrator $sut = null;

    protected function setUp(): void
    {
        $this->sut = new DraftsListJsonPositionMigrator(new DraftsListJsonSegmentFields());
    }

    // --- needsMigration ---

    public function testNeedsMigrationReturnsTrueWhenSegmentsHaveNoTextField(): void
    {
        // Arrange
        $data = $this->buildData(
            'Sehr geehrter Herr Fischer.Freundliche Gruesse.',
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 27],
                ['id' => 'seg-2', 'charStart' => 27, 'charEnd' => 47],
            ]
        );

        // Act & Assert
        self::assertTrue($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseWhenSegmentsHaveTextField(): void
    {
        // Arrange — handled by DraftsListJsonMigrator instead
        $data = $this->buildData(
            '<p>Sky is blue.</p>',
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5, 'text' => '<p>Sky is blue.</p>'],
            ]
        );

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseWhenSegmentMarkAlreadyPresent(): void
    {
        // Arrange
        $data = $this->buildData(
            '<segment-mark data-segment-id="seg-1">Sky is blue.</segment-mark>',
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5],
            ]
        );

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseForEmptySegments(): void
    {
        // Arrange
        $data = $this->buildData('Some text.', []);

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseWhenNoCharStartField(): void
    {
        // Arrange — new format: segments have no charStart
        $data = $this->buildData(
            '<segment-mark data-segment-id="seg-1">Sky is blue.</segment-mark>',
            [
                ['id' => 'seg-1'],
            ]
        );

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsFalseWhenOnlyALaterSegmentHasText(): void
    {
        // Arrange — segment[0] has no text, but seg-2 does; must not be claimed here, since
        // DraftsListJsonMigrator can wrap seg-2 using its reliable text snapshot.
        $data = $this->buildData(
            'Sky is blue.Grass is green.',
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 13],
                ['id' => 'seg-2', 'charStart' => 13, 'charEnd' => 28, 'text' => 'Grass is green.'],
            ]
        );

        // Act & Assert
        self::assertFalse($this->sut->needsMigration($data));
    }

    public function testNeedsMigrationReturnsTrueWhenCharStartIsNull(): void
    {
        // Arrange — charStart key exists but is null; isset()/?? would return false here,
        // array_key_exists() correctly returns true. Mirrors the equivalent fix that was needed
        // in DraftsListJsonMigrator.
        $data = $this->buildData(
            'Sky is blue.',
            [
                ['id' => 'seg-1', 'charStart' => null, 'charEnd' => null],
            ]
        );

        // Act & Assert
        self::assertTrue($this->sut->needsMigration($data));
    }

    // --- migrate ---

    public function testMigrateWrapsUsingPositionsWhenTextFieldMissing(): void
    {
        // Arrange — legacy record: segments carry only charStart/charEnd, no `text` snapshot
        $textualReference = 'Sehr geehrter Herr Fischer.Freundliche Gruesse.';
        $data = $this->buildData(
            $textualReference,
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 27, 'charStartInit' => 0, 'charEndInit' => 27],
                ['id' => 'seg-2', 'charStart' => 27, 'charEnd' => 47, 'charStartInit' => 27, 'charEndInit' => 47],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert
        self::assertSame(
            '<segment-mark data-segment-id="seg-1">Sehr geehrter Herr Fischer.</segment-mark>'
            .'<segment-mark data-segment-id="seg-2">Freundliche Gruesse.</segment-mark>',
            $ref
        );
    }

    public function testMigratePrefersCharStartInitOverCharStartWhenBothPresent(): void
    {
        // Arrange — charStart/charEnd are stale Prosemirror positions (garbled if used as string
        // offsets); charStartInit/charEndInit still align with the actual textualReference.
        $textualReference = 'AAAAABBBBB';
        $data = $this->buildData(
            $textualReference,
            [
                ['id' => 'seg-1', 'charStart' => 2, 'charEnd' => 8, 'charStartInit' => 0, 'charEndInit' => 5],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert
        self::assertSame('<segment-mark data-segment-id="seg-1">AAAAA</segment-mark>BBBBB', $ref);
    }

    public function testMigrateFallsBackToCharStartWhenInitFieldsMissing(): void
    {
        // Arrange — no charStartInit/charEndInit at all
        $textualReference = 'Hello World';
        $data = $this->buildData(
            $textualReference,
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert
        self::assertSame('<segment-mark data-segment-id="seg-1">Hello</segment-mark> World', $ref);
    }

    public function testMigrateFallsBackToLegacyPairWhenOnlyOneInitFieldIsPresent(): void
    {
        // Arrange — charStartInit is present but charEndInit is not. Combining charStartInit (2)
        // with charEnd (5) would wrap the wrong substring ("llo") — the Init pair must be treated
        // as all-or-nothing and fall back to the full charStart/charEnd pair instead.
        $textualReference = 'Hello World';
        $data = $this->buildData(
            $textualReference,
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5, 'charStartInit' => 2],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert
        self::assertSame('<segment-mark data-segment-id="seg-1">Hello</segment-mark> World', $ref);
    }

    public function testMigrateSortsByTheSamePositionFieldUsedForPlacement(): void
    {
        // Arrange — charStart (drifted Prosemirror positions) disagrees with charStartInit
        // (true document order) on which segment comes first. Sorting by charStart while placing
        // by charStartInit would process seg-2 first, advance the cursor past seg-1's start, and
        // cause seg-1 to be silently skipped as "overlapping".
        $textualReference = 'Hello World';
        $data = $this->buildData(
            $textualReference,
            [
                ['id' => 'seg-1', 'charStart' => 100, 'charStartInit' => 0, 'charEndInit' => 5],
                ['id' => 'seg-2', 'charStart' => 50, 'charStartInit' => 6, 'charEndInit' => 11],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert — both segments are wrapped, in true document order
        self::assertSame(
            '<segment-mark data-segment-id="seg-1">Hello</segment-mark> '
            .'<segment-mark data-segment-id="seg-2">World</segment-mark>',
            $ref
        );
    }

    public function testMigrateSkipsSegmentWithOverlappingPositionAndKeepsAllText(): void
    {
        // Arrange — seg-2 overlaps seg-1 (its start is before seg-1's end)
        $textualReference = 'Hello World';
        $data = $this->buildData(
            $textualReference,
            [
                ['id' => 'seg-1', 'charStart' => 0, 'charEnd' => 5, 'charStartInit' => 0, 'charEndInit' => 5],
                ['id' => 'seg-2', 'charStart' => 3, 'charEnd' => 8, 'charStartInit' => 3, 'charEndInit' => 8],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert — seg-2 is skipped rather than corrupting the output, but no character is lost
        self::assertStringContainsString('<segment-mark data-segment-id="seg-1">Hello</segment-mark>', $ref);
        self::assertStringNotContainsString('seg-2', $ref);
        self::assertSame($textualReference, preg_replace('/<segment-mark[^>]*>|<\/segment-mark>/', '', $ref));
    }

    public function testMigrateRemovesPositionFieldsFromAllSegments(): void
    {
        // Arrange
        $data = $this->buildData(
            'Hello World',
            [
                [
                    'id'                  => 'seg-1',
                    'charStart'           => 0,
                    'charEnd'             => 5,
                    'charStartInit'       => 0,
                    'charEndInit'         => 5,
                    'hasProsemirrorIndex' => true,
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

    public function testMigrateHandlesRealWorldRecordWithoutTextField(): void
    {
        // Arrange — reproduces a production draftsListJson record: segments carry only
        // charStart/charEnd/charStartInit/charEndInit, no `text` key, and charStart/charEnd
        // have drifted from raw string offsets while charStartInit/charEndInit have not.
        $textualReference = 'Sehr geehrter Herr Fischer,<br>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, '
            .'sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. '
            .'At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata '
            .'sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed '
            .'diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At '
            .'vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren?<br>Freundliche '
            .'Grüße,<br>Theodor Müller<br><br>Strase 1<br>12345 Berlin';
        $data = $this->buildData(
            $textualReference,
            [
                [
                    'id' => '41d2d28e-3110-4563-b39b-187918160033',
                    'charStart' => 0, 'charEnd' => 560,
                    'charStartInit' => 0, 'charEndInit' => 563,
                ],
                [
                    'id' => '1e85e538-ab51-4ad8-9ae6-b2fa1d9b0608',
                    'charStart' => 564, 'charEnd' => 620,
                    'charStartInit' => 570, 'charEndInit' => 638,
                ],
            ]
        );

        // Act
        $result = $this->sut->migrate($data);
        $ref = $result['data']['attributes']['textualReference'];

        // Assert — both segments are wrapped, using the trustworthy Init positions
        self::assertStringContainsString('<segment-mark data-segment-id="41d2d28e-3110-4563-b39b-187918160033">', $ref);
        self::assertStringContainsString('<segment-mark data-segment-id="1e85e538-ab51-4ad8-9ae6-b2fa1d9b0608">', $ref);
        self::assertStringEndsWith('</segment-mark>', $ref);
        // No character is lost — stripping the inserted marks reproduces the original text
        self::assertSame($textualReference, preg_replace('/<segment-mark[^>]*>|<\/segment-mark>/', '', $ref));

        foreach ($result['data']['attributes']['segments'] as $segment) {
            self::assertArrayNotHasKey('charStart', $segment);
            self::assertArrayNotHasKey('charStartInit', $segment);
        }
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
