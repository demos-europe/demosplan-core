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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\DraftsInfoToSegmentTransformer;
use demosplan\DemosPlanCoreBundle\Validator\DraftsInfoValidator;
use Ramsey\Uuid\Uuid;
use Tests\Base\FunctionalTestCase;

/**
 * Covers the order-based `contentBlocks` write path: a payload with `contentBlocks` instead of
 * `textualReference` + `segments` must pass schema validation and produce real Segment and
 * TextSection entities, correctly ordered.
 */
class DraftsInfoToSegmentTransformerContentBlocksTest extends FunctionalTestCase
{
    protected ?DraftsInfoToSegmentTransformer $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(DraftsInfoToSegmentTransformer::class);
    }

    private function buildContentBlocksPayload(string $statementId, string $procedureId, array $contentBlocks): string
    {
        return json_encode([
            'data' => [
                'id'         => Uuid::uuid4()->toString(),
                'type'       => 'slicing transaction',
                'attributes' => [
                    'statementId'   => $statementId,
                    'procedureId'   => $procedureId,
                    'contentBlocks' => $contentBlocks,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function testTransformBuildsSegmentsAndTextSectionsFromContentBlocks(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->_save();

        $segmentAId = Uuid::uuid4()->toString();
        $segmentBId = Uuid::uuid4()->toString();

        $draftsInfo = $this->buildContentBlocksPayload($statement->getId(), $statement->getProcedureId(), [
            [
                'type'  => 'textSection',
                'order' => 1,
                'text'  => 'Leading unsegmented text.',
            ],
            [
                'type'  => 'segment',
                'order' => 2,
                'id'    => $segmentAId,
                'text'  => '<p>First segment text.</p>',
                'tags'  => [],
            ],
            [
                'type'  => 'textSection',
                'order' => 3,
                'text'  => 'Interlude text.',
            ],
            [
                'type'  => 'segment',
                'order' => 4,
                'id'    => $segmentBId,
                'text'  => '<p>Second segment text.</p>',
                'tags'  => [],
            ],
        ]);

        // Act
        $result = $this->sut->transform($draftsInfo);

        // Assert
        self::assertCount(2, $result['segments']);
        self::assertCount(2, $result['textSections']);

        /** @var Segment $firstSegment */
        $firstSegment = $result['segments'][0];
        self::assertSame($segmentAId, $firstSegment->getId());
        self::assertSame('<p>First segment text.</p>', $firstSegment->getText());
        self::assertSame(2, $firstSegment->getOrderInStatement());

        /** @var Segment $secondSegment */
        $secondSegment = $result['segments'][1];
        self::assertSame($segmentBId, $secondSegment->getId());
        self::assertSame(4, $secondSegment->getOrderInStatement());

        /** @var TextSection $leadingSection */
        $leadingSection = $result['textSections'][0];
        self::assertSame(1, $leadingSection->getOrderInStatement());
        self::assertSame('Leading unsegmented text.', $leadingSection->getText());

        /** @var TextSection $interludeSection */
        $interludeSection = $result['textSections'][1];
        self::assertSame(3, $interludeSection->getOrderInStatement());
        self::assertSame('Interlude text.', $interludeSection->getText());
    }

    public function testTextSectionTextRawIsReadFromItsOwnBlockField(): void
    {
        // Arrange - a textSection block with distinct plain `text` and rich `textRaw`.
        $statement = StatementFactory::createOne();
        $statement->_save();

        $draftsInfo = $this->buildContentBlocksPayload($statement->getId(), $statement->getProcedureId(), [
            [
                'type'    => 'textSection',
                'order'   => 1,
                'text'    => 'Plain leftover text.',
                'textRaw' => '<p>Plain leftover text.</p>',
            ],
            [
                'type'  => 'segment',
                'order' => 2,
                'id'    => Uuid::uuid4()->toString(),
                'text'  => '<p>Segment.</p>',
                'tags'  => [],
            ],
        ]);

        // Act
        $result = $this->sut->transform($draftsInfo);

        // Assert
        /** @var TextSection $textSection */
        $textSection = $result['textSections'][0];
        self::assertSame('Plain leftover text.', $textSection->getText());
        self::assertSame('<p>Plain leftover text.</p>', $textSection->getTextRaw());
    }

    public function testContentBlocksOnlyPayloadPassesSchemaValidation(): void
    {
        // Arrange - no textualReference/segments at all, the exact shape that was previously
        // rejected by the JSON schema before it gained a contentBlocks branch.
        $statement = StatementFactory::createOne();
        $statement->_save();

        $draftsInfo = $this->buildContentBlocksPayload($statement->getId(), $statement->getProcedureId(), [
            ['type' => 'textSection', 'order' => 1, 'text' => 'Some text.'],
        ]);

        $validator = $this->getContainer()->get(DraftsInfoValidator::class);

        // Act & Assert - no exception means the schema accepted the contentBlocks-only shape.
        $validator->validate($draftsInfo);
        self::assertTrue(true);
    }
}
