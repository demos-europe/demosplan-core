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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\DraftsInfoToSegmentTransformer;
use Tests\Base\FunctionalTestCase;

/**
 * Covers which <segment-mark> elements become Segment entities during
 * finalization.
 *
 * A mark is materialized only when its data-segment-id is present in the
 * schema-validated `segments` metadata AND is a valid UUID. A planner-confirmed
 * segment always satisfies both (the FE writes a matching metadata entry and the
 * schema forces those ids to be 36 chars), so it is never dropped. Marks that
 * fail either gate are leftover pipeline placeholders (content the pipeline could
 * not classify, e.g. images or un-OCR-able tables) or corrupted ids that would
 * otherwise be persisted verbatim as an invalid primary key.
 */
class DraftsInfoToSegmentTransformerTest extends FunctionalTestCase
{
    protected $sut;

    private const CONFIRMED_ID = 'a1111111-1111-4111-8111-111111111111';
    private const SECOND_CONFIRMED_ID = 'b2222222-2222-4222-8222-222222222222';
    private const ORPHAN_VALID_UUID = 'c3333333-3333-4333-8333-333333333333';
    private const NON_UUID_ID = '21929_24762';

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
     * dropped?": only marks that are absent from the metadata or carry a
     * non-UUID id are dropped. A confirmed mark interleaved with them still
     * survives, and the extern-id counter does not leave gaps for skipped marks.
     */
    public function testUnconfirmedAndNonUuidMarksAreDroppedButConfirmedSurvives(): void
    {
        $statement = StatementFactory::createOne(['externId' => 'ST-1']);
        $procedure = $statement->getProcedure();
        PlaceFactory::createOne(['procedure' => $procedure]);

        // Order: valid-but-unconfirmed, confirmed, corrupted-id. Only the middle
        // one is listed in the segments metadata below.
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
