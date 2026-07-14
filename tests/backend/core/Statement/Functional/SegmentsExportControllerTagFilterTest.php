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

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Functions\OneOf;
use Symfony\Contracts\Translation\TranslatorInterface;
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

    private ?StatementResourceType $statementResourceType = null;
    private ?DqlConditionFactory $conditionFactory = null;
    private Proxy|Procedure|null $testProcedure = null;
    private Proxy|TagTopic|null $tagTopic1 = null;
    private Proxy|TagTopic|null $tagTopic2 = null;
    private Proxy|Tag|null $tag1 = null;
    private Proxy|Tag|null $tag2 = null;
    private Proxy|Tag|null $tag3 = null;
    private Proxy|StatementInterface|null $statement1 = null;
    private Proxy|StatementInterface|null $statement2 = null;
    private Proxy|StatementInterface|null $statement3 = null;
    private ?SegmentInterface $segment1 = null;
    private ?SegmentInterface $segment2 = null;
    private ?SegmentInterface $segment3 = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Instantiate the filter service
        /** @var TranslatorInterface $translator */
        $translator = $this->getContainer()->get(TranslatorInterface::class);
        $this->conditionFactory = $this->getContainer()->get(DqlConditionFactory::class);
        $this->statementResourceType = $this->getContainer()->get(StatementResourceType::class);
        $this->sut = new StatementExportTagFilter($translator, $this->getEntityManager(), $this->conditionFactory);

        // Create a procedure owned by the test user's orga, with settings, so the
        // StatementResourceType access conditions allow querying its statements. This is
        // required by the query-path tests that exercise buildStatementTagConditions()
        // through StatementResourceType::getEntities().
        $currentCustomer = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer();
        $orga = OrgaFactory::createOne();
        $this->testProcedure = ProcedureFactory::createOne(['orga' => $orga, 'customer' => $currentCustomer]);
        ProcedureSettingsFactory::createOne(['procedure' => $this->testProcedure]);

        $this->statement1 = StatementFactory::createOne(['procedure' => $this->testProcedure->_real()]);
        $this->statement2 = StatementFactory::createOne(['procedure' => $this->testProcedure->_real()]);
        $this->statement3 = StatementFactory::createOne(['procedure' => $this->testProcedure->_real()]);

        // Create test tag topics and tags
        $this->tagTopic1 = TagTopicFactory::createOne(['title' => self::TAG_TOPIC_NAME_1]);
        $this->tagTopic2 = TagTopicFactory::createOne(['title' => self::TAG_TOPIC_NAME_2]);

        $this->tag1 = TagFactory::createOne(['title' => 'Important', 'topic' => $this->tagTopic1->_real()]);
        $this->tag2 = TagFactory::createOne(['title' => 'Urgent', 'topic' => $this->tagTopic1->_real()]);
        $this->tag3 = TagFactory::createOne(['title' => 'Review', 'topic' => $this->tagTopic2->_real()]);

        // Create basic segments for common tests (without tags - tests will add tags as needed)
        $this->segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])->_real();
        $this->segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement2->_real()])->_real();
        $this->segment3 = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement3->_real()])->_real();

        // Make the procedure current and log in a user of its orga, so getEntities() on the
        // StatementResourceType resolves against this procedure in the query-path tests.
        $this->getUserReference('testUser')->setOrga($orga->_real());
        $currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->testProcedure->_real());
        $this->statementResourceType->setCurrentProcedureService($currentProcedureService);
        $this->loginTestUser();
        $this->enablePermissions([
            'feature_json_api_statement',
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);
    }

    public function testFilterStatementsByTagIds(): void
    {
        // Arrange: Add tags to segments
        $this->segment1->addTag($this->tag1->_real());
        $this->segment2->addTag($this->tag2->_real());
        $this->segment3->addTag($this->tag3->_real());
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
        // Arrange: Add tags to segments
        $this->segment1->addTag($this->tag1->_real());
        $this->segment2->addTag($this->tag2->_real());
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
        // Arrange: Add tags to segments
        $this->segment1->addTag($this->tag1->_real()); // Topic 1
        $this->segment2->addTag($this->tag2->_real()); // Topic 1
        $this->segment3->addTag($this->tag3->_real()); // Topic 2
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
        // Arrange: Add tags to segments
        $this->segment1->addTag($this->tag1->_real()); // Topic 1
        $this->segment2->addTag($this->tag3->_real()); // Topic 2
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
        // Arrange: Add tags to segments
        $this->segment1->addTag($this->tag1->_real()); // Important, Topic 1
        $this->segment2->addTag($this->tag2->_real()); // Urgent, Topic 1
        $this->segment3->addTag($this->tag3->_real()); // Review, Topic 2
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
        // Arrange: Add one more segment to statement1 (already has segment1 from setUp)
        SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])->_real();
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
        // Arrange: Add tag to segment
        $this->segment1->addTag($this->tag1->_real());
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
        $this->segment1->addTag($this->tag1->_real());

        /** @var SegmentInterface $segment1b */
        $segment1b = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1b->addTag($this->tag2->_real());

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
        static::assertSame($this->segment1->getId(), $includedSegment->getId());
    }

    public function testFilterStatementsWithSegmentHavingMultipleTags(): void
    {
        // Arrange: Add multiple tags to segment
        $this->segment1->addTag($this->tag1->_real());
        $this->segment1->addTag($this->tag2->_real());
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
        // Arrange: Use pre-created segments and create one more per statement, add tags
        // Statement 1 - Use segment1: has tag1 (Topic 1) and tag2 (Topic 1)
        $this->segment1->addTag($this->tag1->_real()); // Important, Topic 1
        $this->segment1->addTag($this->tag2->_real()); // Urgent, Topic 1

        // Statement 1 - Create segment1b: has tag1 (Topic 1) and tag2 (Topic 1)
        /** @var SegmentInterface $segment1b */
        $segment1b = SegmentFactory::createOne(['parentStatementOfSegment' => $this->statement1->_real()])
            ->_real();
        $segment1b->addTag($this->tag1->_real()); // Important, Topic 1
        $segment1b->addTag($this->tag2->_real()); // Urgent, Topic 1

        // Statement 2 - Use segment2: has tag1 (Topic 1) and tag3 (Topic 2)
        $this->segment2->addTag($this->tag1->_real()); // Important, Topic 1
        $this->segment2->addTag($this->tag3->_real()); // Review, Topic 2

        // Statement 2 - Create segment2b: has tag1 (Topic 1) and tag2 (Topic 1)
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

        // Assert: Only statement2 should be returned with only segment2 (the one with Topic 2)
        static::assertCount(1, $filtered, 'Only statement2 should match');

        $filteredStatement = reset($filtered);
        static::assertSame($this->statement2->_real()->getId(), $filteredStatement->getId(), 'The returned statement should be statement2');
        static::assertCount(1, $filteredStatement->getSegmentsOfStatement(), 'Only one segment should remain');

        // Verify the correct segment is included
        $includedSegment = $filteredStatement->getSegmentsOfStatement()->first();
        static::assertSame($this->segment2->getId(), $includedSegment->getId(), 'The included segment should be segment2');

        // Verify the segment has both tags (tag1 and tag3)
        static::assertCount(2, $includedSegment->getTags(), 'The segment should have 2 tags');

        // Verify statement1 was excluded entirely
        $filteredIds = array_map(fn ($s) => $s->getId(), $filtered);
        static::assertNotContains($this->statement1->_real()->getId(), $filteredIds, 'Statement1 should be excluded');
    }

    public function testBuildStatementTagConditionsWithEmptyFilterReturnsEmpty(): void
    {
        // Act: no supported criteria present
        $conditions = $this->sut->buildStatementTagConditions([], $this->statementResourceType);

        // Assert: query is left unchanged (full export)
        static::assertSame([], $conditions);
    }

    public function testBuildStatementTagConditionsWithSingleCriterionReturnsStatementIdCondition(): void
    {
        // Arrange: statement1 owns a segment carrying tag1, so the id resolution finds a match
        $this->segment1->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        // Act: exactly one criterion
        $conditions = $this->sut->buildStatementTagConditions(
            ['tagIds' => [$this->tag1->getId()]],
            $this->statementResourceType
        );

        // Assert: a single `statement.id IN (...)` condition (OneOf), rather than a to-many
        // condition on segments.tags.* (which would fetch-join and exhaust memory on large procedures)
        static::assertCount(1, $conditions);
        static::assertInstanceOf(OneOf::class, $conditions[0]);
    }

    public function testBuildStatementTagConditionsWithMultipleCriteriaReturnsSingleStatementIdCondition(): void
    {
        // Arrange: statement1 matched by tag id, statement3 matched by topic title
        $this->segment1->addTag($this->tag1->_real()); // Topic 1
        $this->segment3->addTag($this->tag3->_real()); // Topic 2
        $this->getEntityManager()->flush();

        // Act: two criteria, OR-combined while resolving the matching statement ids
        $conditions = $this->sut->buildStatementTagConditions(
            [
                'tagIds'         => [$this->tag1->getId()],
                'tagTopicTitles' => [self::TAG_TOPIC_NAME_2],
            ],
            $this->statementResourceType
        );

        // Assert: still a single `statement.id IN (...)` condition covering the union of both criteria
        static::assertCount(1, $conditions);
        static::assertInstanceOf(OneOf::class, $conditions[0]);
    }

    public function testBuildStatementTagConditionsWithNoMatchForcesEmptyResult(): void
    {
        // Act: criteria present but no segment carries the tag
        $conditions = $this->sut->buildStatementTagConditions(
            ['tagIds' => ['non-existent-id']],
            $this->statementResourceType
        );

        // Assert: a single condition that matches nothing is returned, so the export yields an
        // empty result instead of silently falling back to an unfiltered full export
        static::assertCount(1, $conditions);
        static::assertCount(0, $this->statementResourceType->getEntities($conditions, []));
    }

    public function testPushedDownFilterReturnsSameStatementsAsInPhpFilter(): void
    {
        // Arrange: three visible (non-original) statements, each with one tagged segment
        $stmtWithTag1 = $this->createVisibleStatementWithTaggedSegment($this->tag1->_real());
        $stmtWithTag2 = $this->createVisibleStatementWithTaggedSegment($this->tag2->_real());
        $stmtWithTag3 = $this->createVisibleStatementWithTaggedSegment($this->tag3->_real());
        $this->getEntityManager()->flush();

        $tagsFilter = ['tagIds' => [$this->tag1->getId()]];

        // Act: in-PHP filter over the full candidate set ...
        $phpFilteredIds = array_map(
            static fn (StatementInterface $s): string => $s->getId(),
            $this->sut->filterStatementsByTags([$stmtWithTag1, $stmtWithTag2, $stmtWithTag3], $tagsFilter)
        );

        // ... and the pushed-down query filter through the resource type
        $conditions = $this->sut->buildStatementTagConditions($tagsFilter, $this->statementResourceType);
        $queryFilteredIds = array_map(
            static fn (StatementInterface $s): string => $s->getId(),
            $this->statementResourceType->getEntities($conditions, [])
        );

        // Assert: both paths select the same statement, and only that one
        sort($phpFilteredIds);
        sort($queryFilteredIds);
        static::assertSame([$stmtWithTag1->getId()], $phpFilteredIds);
        static::assertSame($phpFilteredIds, $queryFilteredIds);
    }

    public function testStatementWithTwoMatchingSegmentsIsReturnedOnce(): void
    {
        // Arrange: a single statement whose TWO segments both carry the filtered tag.
        // The statement-id resolution joins segments+tags; this asserts the to-many join
        // does not surface the statement more than once (the id query is DISTINCT).
        $statement = $this->createVisibleStatement();
        $segmentA = SegmentFactory::createOne(['parentStatementOfSegment' => $statement])->_real();
        $segmentB = SegmentFactory::createOne(['parentStatementOfSegment' => $statement])->_real();
        $segmentA->addTag($this->tag1->_real());
        $segmentB->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        // Act
        $conditions = $this->sut->buildStatementTagConditions(
            ['tagIds' => [$this->tag1->getId()]],
            $this->statementResourceType
        );
        $result = $this->statementResourceType->getEntities($conditions, []);

        // Assert: exactly one statement, no duplicate ids
        $ids = array_map(static fn (StatementInterface $s): string => $s->getId(), $result);
        static::assertCount(1, $result);
        static::assertSame([$statement->getId()], $ids);
        static::assertSame($ids, array_values(array_unique($ids)));
    }

    /**
     * Creates a statement that passes the StatementResourceType access conditions:
     * non-deleted, in the current procedure, and a copy (has an original).
     */
    private function createVisibleStatement(): StatementInterface
    {
        $original = StatementFactory::createOne(['procedure' => $this->testProcedure->_real()])->_real();

        return StatementFactory::createOne([
            'procedure' => $this->testProcedure->_real(),
            'original'  => $original,
        ])->_real();
    }

    private function createVisibleStatementWithTaggedSegment(Tag $tag): StatementInterface
    {
        $statement = $this->createVisibleStatement();
        $segment = SegmentFactory::createOne(['parentStatementOfSegment' => $statement])->_real();
        $segment->addTag($tag);

        return $statement;
    }
}
