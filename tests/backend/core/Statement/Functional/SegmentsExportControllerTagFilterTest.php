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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class SegmentsExportControllerTagFilterTest extends FunctionalTestCase
{
    private const TAG_TOPIC_NAME_1 = 'Topic 1';
    private const TAG_TOPIC_NAME_2 = 'Topic 2';

    /**
     * @var StatementExportTagFilter
     */
    protected $sut;

    private Proxy|TagTopic|null $tagTopic1 = null;
    private Proxy|TagTopic|null $tagTopic2 = null;
    private Proxy|Tag|null $tag1 = null;
    private Proxy|Tag|null $tag2 = null;
    private Proxy|Tag|null $tag3 = null;
    private Proxy|StatementInterface|null $statement1 = null;
    private Proxy|StatementInterface|null $statement2 = null;
    private Proxy|StatementInterface|null $statement3 = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Instantiate the filter service
        $this->sut = new StatementExportTagFilter();

        // Create test procedure and statements used by most tests
        $testProcedure = ProcedureFactory::createOne();
        $this->statement1 = StatementFactory::createOne(['procedure' => $testProcedure->_real()]);
        $this->statement2 = StatementFactory::createOne(['procedure' => $testProcedure->_real()]);
        $this->statement3 = StatementFactory::createOne(['procedure' => $testProcedure->_real()]);

        // Create test tag topics and tags
        $this->tagTopic1 = TagTopicFactory::createOne(['title' => self::TAG_TOPIC_NAME_1]);
        $this->tagTopic2 = TagTopicFactory::createOne(['title' => self::TAG_TOPIC_NAME_2]);

        $this->tag1 = TagFactory::createOne(['title' => 'Important', 'topic' => $this->tagTopic1->_real()]);
        $this->tag2 = TagFactory::createOne(['title' => 'Urgent', 'topic' => $this->tagTopic1->_real()]);
        $this->tag3 = TagFactory::createOne(['title' => 'Review', 'topic' => $this->tagTopic2->_real()]);
    }

    public function testFilterStatementsByTagIds(): void
    {
        // Arrange: Create segments with different tags
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real());
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment3 */
        $segment3 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement3->_real()])
            ->_real();
        $segment3->addTag($this->tag3->_real());
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real(), $this->statement2->_real(), $this->statement3->_real()];

        // Act: Filter by tag1's ID
        $tagsFilter = ['tagIds' => [$this->tag1->getId()]];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: Only statement1 should be included with 1 segment
        static::assertCount(1, $filtered);
        $filteredStatement = reset($filtered);
        static::assertSame($this->statement1->_real()->getId(), $filteredStatement->getId());
        static::assertCount(1, $filteredStatement->getSegmentsOfStatement());
    }

    public function testFilterStatementsByTagTitles(): void
    {
        // Arrange
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real());
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real(), $this->statement2->_real()];

        // Act: Filter by tag titles
        $tagsFilter = ['tagTitles' => ['Important', 'Urgent']];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: Both statements should be included, each with 1 segment
        static::assertCount(2, $filtered);
        foreach ($filtered as $filteredStatement) {
            static::assertCount(1, $filteredStatement->getSegmentsOfStatement());
        }
    }

    public function testFilterStatementsByTagTopicIds(): void
    {
        // Arrange
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real()); // Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real()); // Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment3 */
        $segment3 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement3->_real()])
            ->_real();
        $segment3->addTag($this->tag3->_real()); // Topic 2
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real(), $this->statement2->_real(), $this->statement3->_real()];

        // Act: Filter by tagTopic1's ID
        $tagsFilter = ['tagTopicIds' => [$this->tagTopic1->getId()]];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: statement1 and statement2 should be included, each with 1 segment
        static::assertCount(2, $filtered);
        $filteredIds = array_map(fn ($s) => $s->getId(), $filtered);
        static::assertContains($this->statement1->_real()->getId(), $filteredIds);
        static::assertContains($this->statement2->_real()->getId(), $filteredIds);
        foreach ($filtered as $filteredStatement) {
            static::assertCount(1, $filteredStatement->getSegmentsOfStatement());
        }
    }

    public function testFilterStatementsByTagTopicTitles(): void
    {
        // Arrange
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real()); // Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag3->_real()); // Topic 2
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real(), $this->statement2->_real()];

        // Act: Filter by topic title
        $tagsFilter = ['tagTopicTitles' => [self::TAG_TOPIC_NAME_2]];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: Only statement2 should be included with 1 segment
        static::assertCount(1, $filtered);
        $filteredStatement = reset($filtered);
        static::assertSame($this->statement2->_real()->getId(), $filteredStatement->getId());
        static::assertCount(1, $filteredStatement->getSegmentsOfStatement());
    }

    public function testFilterStatementsByMultipleCriteria(): void
    {
        // Arrange
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real()); // Important, Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real()); // Urgent, Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment3 */
        $segment3 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement3->_real()])
            ->_real();
        $segment3->addTag($this->tag3->_real()); // Review, Topic 2
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real(), $this->statement2->_real(), $this->statement3->_real()];

        // Act: Filter by tag ID OR topic title (should match statement1 and statement3)
        $tagsFilter = [
            'tagIds'         => [$this->tag1->getId()], // statement1
            'tagTopicTitles' => [self::TAG_TOPIC_NAME_2], // statement3
        ];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: statement1 and statement3 should be included, each with 1 segment
        static::assertCount(2, $filtered);
        $filteredIds = array_map(fn ($s) => $s->getId(), $filtered);
        static::assertContains($this->statement1->_real()->getId(), $filteredIds);
        static::assertContains($this->statement3->_real()->getId(), $filteredIds);
        foreach ($filtered as $filteredStatement) {
            static::assertCount(1, $filteredStatement->getSegmentsOfStatement());
        }
    }

    public function testFilterStatementsWithEmptyFilter(): void
    {
        // Arrange: Add segments to statements
        /* @var SegmentInterface $segment1 */
        SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        /* @var SegmentInterface $segment2 */
        SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        /* @var SegmentInterface $segment3 */
        SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real(), $this->statement2->_real()];

        // Act: Filter with empty array
        $tagsFilter = [];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: All statements should be returned with all segments unchanged
        static::assertCount(2, $filtered);
        static::assertSame($statements, $filtered);
        static::assertCount(2, $this->statement1->_real()->getSegmentsOfStatement());
        static::assertCount(1, $this->statement2->_real()->getSegmentsOfStatement());
    }

    public function testFilterStatementsWithNoMatchingTags(): void
    {
        // Arrange
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real()];

        // Act: Filter by non-existent tag ID
        $tagsFilter = ['tagIds' => ['non-existent-id']];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: No statements should be returned (segment has tags but they don't match)
        static::assertCount(0, $filtered);
    }

    public function testFilterStatementsWithMultipleSegmentsPerStatement(): void
    {
        // Arrange: Statement with multiple segments, only one has matching tag
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real());

        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real()];

        // Act: Filter by tag1 only
        $tagsFilter = ['tagIds' => [$this->tag1->getId()]];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: Statement should be included with only 1 segment (segment1 with tag1)
        static::assertCount(1, $filtered);
        $filteredStatement = reset($filtered);
        static::assertSame($this->statement1->_real()->getId(), $filteredStatement->getId());
        static::assertCount(1, $filteredStatement->getSegmentsOfStatement(), 'Only segment with tag1 should be included');

        // Verify the included segment is segment1
        $includedSegment = $filteredStatement->getSegmentsOfStatement()->first();
        static::assertSame($segment1->getId(), $includedSegment->getId());
    }

    public function testFilterStatementsWithSegmentHavingMultipleTags(): void
    {
        // Arrange: Segment with multiple tags
        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $segment1->addTag($this->tag2->_real());
        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real()];

        // Act: Filter by tag2
        $tagsFilter = ['tagIds' => [$this->tag2->getId()]];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: Statement should be included with 1 segment
        static::assertCount(1, $filtered);
        $filteredStatement = reset($filtered);
        static::assertSame($this->statement1->_real()->getId(), $filteredStatement->getId());
        static::assertCount(1, $filteredStatement->getSegmentsOfStatement());

        // Verify the segment has both tags
        $includedSegment = $filteredStatement->getSegmentsOfStatement()->first();
        static::assertCount(2, $includedSegment->getTags());
    }

    public function testComplexFilterWithMultipleStatementsAndSegmentsFilteredByTopicTitle(): void
    {
        // Arrange: Create 2 statements, each with 2 segments, each segment with multiple tags
        // Statement 1 - Segment 1a: has tag1 (Topic 1) and tag2 (Topic 1)
        /** @var SegmentInterface $segment1a */
        $segment1a = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1a->addTag($this->tag1->_real()); // Important, Topic 1
        $segment1a->addTag($this->tag2->_real()); // Urgent, Topic 1

        // Statement 1 - Segment 1b: has tag1 (Topic 1) and tag2 (Topic 1)
        /** @var SegmentInterface $segment1b */
        $segment1b = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1b->addTag($this->tag1->_real()); // Important, Topic 1
        $segment1b->addTag($this->tag2->_real()); // Urgent, Topic 1

        // Statement 2 - Segment 2a: has tag1 (Topic 1) and tag3 (Topic 2)
        /** @var SegmentInterface $segment2a */
        $segment2a = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $segment2a->addTag($this->tag1->_real()); // Important, Topic 1
        $segment2a->addTag($this->tag3->_real()); // Review, Topic 2

        // Statement 2 - Segment 2b: has tag1 (Topic 1) and tag2 (Topic 1)
        /** @var SegmentInterface $segment2b */
        $segment2b = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])
            ->_real();
        $segment2b->addTag($this->tag1->_real()); // Important, Topic 1
        $segment2b->addTag($this->tag2->_real()); // Urgent, Topic 1

        $this->getEntityManager()->flush();

        $statements = [$this->statement1->_real(), $this->statement2->_real()];

        // Verify initial state: 2 statements, each with 2 segments
        static::assertCount(2, $this->statement1->_real()->getSegmentsOfStatement());
        static::assertCount(2, $this->statement2->_real()->getSegmentsOfStatement());

        // Act: Filter by Topic 2 title
        $tagsFilter = ['tagTopicTitles' => [self::TAG_TOPIC_NAME_2]];
        $filtered = $this->sut->filterStatementsByTags($statements, $tagsFilter);

        // Assert: Only statement2 should be returned with only segment2a
        static::assertCount(1, $filtered, 'Only statement2 should match');

        $filteredStatement = reset($filtered);
        static::assertSame($this->statement2->_real()->getId(), $filteredStatement->getId(), 'The returned statement should be statement2');
        static::assertCount(1, $filteredStatement->getSegmentsOfStatement(), 'Only one segment should remain');

        // Verify the correct segment is included
        $includedSegment = $filteredStatement->getSegmentsOfStatement()->first();
        static::assertSame($segment2a->getId(), $includedSegment->getId(), 'The included segment should be segment2a');

        // Verify the segment has both tags (tag1 and tag3)
        static::assertCount(2, $includedSegment->getTags(), 'The segment should have 2 tags');

        // Verify statement1 was excluded entirely
        $filteredIds = array_map(fn ($s) => $s->getId(), $filtered);
        static::assertNotContains($this->statement1->_real()->getId(), $filteredIds, 'Statement1 should be excluded');
    }
}
