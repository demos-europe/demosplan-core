<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\DraftsInfoToSegmentTransformer;
use Tests\Base\FunctionalTestCase;

/**
 * Covers which <segment-mark> elements become Segment entities during
 * finalization.
 *
 * A mark is materialized only when its data-segment-id is present in the
 * schema-validated `segments` metadata AND is a valid UUID. The presence check is
 * the one that matters in practice: a real segment always has a metadata entry
 * (the FE writes one, and auto-confirms any remaining proposal before the final
 * save), while leftover pipeline placeholders — content the pipeline could not
 * classify, e.g. images or un-OCR-able tables — do not. The UUID check is a
 * safeguard against an id that would otherwise be persisted verbatim as the
 * entity primary key.
 */
class DraftsInfoToSegmentTransformerTest extends FunctionalTestCase
{
    protected ?DraftsInfoToSegmentTransformer $sut = null;

    private const CONFIRMED_ID = 'a1111111-1111-4111-8111-111111111111';
    private const SECOND_CONFIRMED_ID = 'b2222222-2222-4222-8222-222222222222';
    private const ORPHAN_VALID_UUID = 'c3333333-3333-4333-8333-333333333333';
    private const NON_UUID_ID = '21929_24762';
    /** 36 chars, so it passes the schema, but not a UUID — the only input that reaches the UUID gate. */
    private const METADATA_NON_UUID_ID = 'not-a-uuid-but-exactly-36-chars-long';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(DraftsInfoToSegmentTransformer::class);
    }

    /**
     * A confirmed segment (mark id present in metadata, valid UUID) is
     * materialized; it is never dropped by the skip logic.
     */
    public function testConfirmedSegmentsAreMaterialized(): void
    {
        $statement = StatementFactory::createOne(['externId' => 'ST-1']);
        $procedure = $statement->getProcedure();
        PlaceFactory::createOne(['procedure' => $procedure]);

        $textualReference =
            '<p><segment-mark data-segment-id="'.self::CONFIRMED_ID.'">First confirmed</segment-mark></p>'
            .'<p><segment-mark data-segment-id="'.self::SECOND_CONFIRMED_ID.'">Second confirmed</segment-mark></p>';

        $draftsInfo = $this->buildDraftsInfo(
            $statement->getId(),
            $procedure->getId(),
            $textualReference,
            [
                ['id' => self::CONFIRMED_ID, 'tags' => []],
                ['id' => self::SECOND_CONFIRMED_ID, 'tags' => []],
            ]
        );

        $segments = $this->sut->transform($draftsInfo);

        self::assertCount(2, $segments);
        self::assertSame(
            [self::CONFIRMED_ID, self::SECOND_CONFIRMED_ID],
            array_map(static fn (Segment $segment): string => $segment->getId(), $segments)
        );
        self::assertStringContainsString('First confirmed', $segments[0]->getText());
        // extern ids stay contiguous and keyed off the parent statement.
        self::assertSame('ST-1-1', $segments[0]->getExternId());
        self::assertSame('ST-1-2', $segments[1]->getExternId());
    }

    /**
     * Answers the review question "is a visually marked part now silently
     * dropped?": marks with no entry in the segments metadata are dropped by the
     * presence check. Both the orphan UUID and the malformed pipeline id below
     * fail there, before the UUID gate is ever reached. A mark that does have
     * metadata and is interleaved with them still survives, and the extern-id
     * counter does not leave gaps for skipped marks.
     */
    public function testMarksWithoutMetadataEntryAreSkippedWhileOthersSurvive(): void
    {
        $statement = StatementFactory::createOne(['externId' => 'ST-1']);
        $procedure = $statement->getProcedure();
        PlaceFactory::createOne(['procedure' => $procedure]);

        // Order: orphan UUID, segment with metadata, corrupted pipeline id. Only
        // the middle one is listed in the segments metadata below.
        $textualReference =
            '<p><segment-mark data-segment-id="'.self::ORPHAN_VALID_UUID.'">Not in metadata</segment-mark></p>'
            .'<p><segment-mark data-segment-id="'.self::CONFIRMED_ID.'">Confirmed segment</segment-mark></p>'
            .'<p><segment-mark data-segment-id="'.self::NON_UUID_ID.'">Corrupted id</segment-mark></p>';

        $draftsInfo = $this->buildDraftsInfo(
            $statement->getId(),
            $procedure->getId(),
            $textualReference,
            [['id' => self::CONFIRMED_ID, 'tags' => []]]
        );

        $segments = $this->sut->transform($draftsInfo);

        self::assertCount(1, $segments);
        self::assertSame(self::CONFIRMED_ID, $segments[0]->getId());
        self::assertStringContainsString('Confirmed segment', $segments[0]->getText());
        self::assertSame('ST-1-1', $segments[0]->getExternId());

        $materializedIds = array_map(static fn (Segment $segment): string => $segment->getId(), $segments);
        self::assertNotContains(self::ORPHAN_VALID_UUID, $materializedIds);
        self::assertNotContains(self::NON_UUID_ID, $materializedIds);
    }

    /**
     * Exercises the UUID gate itself, which the tests above cannot reach.
     *
     * An id only gets that far if it is present in the segments metadata, and the
     * schema pins segments[].id to exactly 36 chars — anything else is rejected by
     * DraftsInfoValidator before the transformer runs, and anything missing from
     * the metadata is already dropped by the presence check. That leaves exactly
     * one input: a 36-char id that is not a UUID. It must be skipped rather than
     * persisted verbatim as the entity primary key.
     */
    public function testMetadataSegmentWithNonUuidIdIsSkipped(): void
    {
        $statement = StatementFactory::createOne(['externId' => 'ST-1']);
        $procedure = $statement->getProcedure();
        PlaceFactory::createOne(['procedure' => $procedure]);

        // Guards the point of this test: at any other length the schema would
        // reject the payload and the UUID gate would never be reached.
        self::assertSame(36, strlen(self::METADATA_NON_UUID_ID));

        $textualReference =
            '<p><segment-mark data-segment-id="'.self::METADATA_NON_UUID_ID.'">Invalid id</segment-mark></p>'
            .'<p><segment-mark data-segment-id="'.self::CONFIRMED_ID.'">Valid id</segment-mark></p>';

        $draftsInfo = $this->buildDraftsInfo(
            $statement->getId(),
            $procedure->getId(),
            $textualReference,
            [
                ['id' => self::METADATA_NON_UUID_ID, 'tags' => []],
                ['id' => self::CONFIRMED_ID, 'tags' => []],
            ]
        );

        $segments = $this->sut->transform($draftsInfo);

        self::assertCount(1, $segments);
        self::assertSame(self::CONFIRMED_ID, $segments[0]->getId());
        self::assertStringContainsString('Valid id', $segments[0]->getText());
        // The skipped mark came first, so the surviving segment still starts at -1.
        self::assertSame('ST-1-1', $segments[0]->getExternId());
    }

    /**
     * Guards the tag rekeying, the latent bug this change fixes.
     *
     * Tags are applied in a second pass after persist. That loop used to read
     * $parsedMarks by the position of the segment within $segments, which only
     * lines up while every mark becomes a segment. The moment one mark is skipped
     * the two lists drift and each segment receives a different segment's tags.
     *
     * The skipped mark below is deliberately first, so under the old positional
     * lookup the first segment would end up with no tags at all and the second
     * would inherit the first segment's tags.
     */
    public function testTagsStayWithTheirOwnSegmentWhenAMarkIsSkipped(): void
    {
        $statement = StatementFactory::createOne(['externId' => 'ST-1']);
        $procedure = $statement->getProcedure();
        PlaceFactory::createOne(['procedure' => $procedure]);

        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $firstTag = TagFactory::createOne(['title' => 'First tag', 'topic' => $topic]);
        $secondTag = TagFactory::createOne(['title' => 'Second tag', 'topic' => $topic]);

        // Order: skipped orphan, segment tagged 'First tag', segment tagged 'Second tag'.
        $textualReference =
            '<p><segment-mark data-segment-id="'.self::ORPHAN_VALID_UUID.'">Orphan</segment-mark></p>'
            .'<p><segment-mark data-segment-id="'.self::CONFIRMED_ID.'">First</segment-mark></p>'
            .'<p><segment-mark data-segment-id="'.self::SECOND_CONFIRMED_ID.'">Second</segment-mark></p>';

        $draftsInfo = $this->buildDraftsInfo(
            $statement->getId(),
            $procedure->getId(),
            $textualReference,
            [
                [
                    'id'   => self::CONFIRMED_ID,
                    'tags' => [['id' => $firstTag->getId(), 'tagName' => 'First tag']],
                ],
                [
                    'id'   => self::SECOND_CONFIRMED_ID,
                    'tags' => [['id' => $secondTag->getId(), 'tagName' => 'Second tag']],
                ],
            ]
        );

        $segments = $this->sut->transform($draftsInfo);

        self::assertCount(2, $segments);
        self::assertSame(self::CONFIRMED_ID, $segments[0]->getId());
        self::assertSame(['First tag'], $this->tagTitlesOf($segments[0]));
        self::assertSame(self::SECOND_CONFIRMED_ID, $segments[1]->getId());
        self::assertSame(['Second tag'], $this->tagTitlesOf($segments[1]));
    }

    /**
     * @return list<string>
     */
    private function tagTitlesOf(Segment $segment): array
    {
        return array_values(
            $segment->getTags()
                ->map(static fn (Tag $tag): string => $tag->getTitle())
                ->toArray()
        );
    }

    /**
     * @param list<array{id: string, tags: array<mixed>}> $segmentsMetadata
     */
    private function buildDraftsInfo(
        string $statementId,
        string $procedureId,
        string $textualReference,
        array $segmentsMetadata,
    ): string {
        return json_encode([
            'data' => [
                'id'         => self::CONFIRMED_ID, // any 36-char id; unused by the transformer
                'type'       => 'draftStatementSegments',
                'attributes' => [
                    'statementId'      => $statementId,
                    'procedureId'      => $procedureId,
                    'textualReference' => $textualReference,
                    'segments'         => $segmentsMetadata,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
