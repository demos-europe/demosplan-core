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
use demosplan\DemosPlanCoreBundle\Controller\Segment\SegmentsExportController;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class SegmentsExportControllerTagFilterTest extends FunctionalTestCase
{
    /**
     * @var SegmentsExportController
     */
    protected $sut;

    private Proxy|TagTopic|null $tagTopic1 = null;
    private Proxy|TagTopic|null $tagTopic2 = null;
    private Proxy|Tag|null $tag1 = null;
    private Proxy|Tag|null $tag2 = null;
    private Proxy|Tag|null $tag3 = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Manually instantiate the controller with required dependencies
        /** @var NameGenerator $nameGenerator */
        $nameGenerator = $this->getContainer()->get(\demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator::class);
        /** @var ProcedureHandler $procedureHandler */
        $procedureHandler = $this->getContainer()->get(\demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler::class);
        /** @var RequestStack $requestStack */
        $requestStack = $this->getContainer()->get(RequestStack::class);

        $this->sut = new SegmentsExportController(
            $nameGenerator,
            $procedureHandler,
            $requestStack
        );

        // Create test tag topics and tags
        $this->tagTopic1 = TagTopicFactory::createOne(['title' => 'Topic 1']);
        $this->tagTopic2 = TagTopicFactory::createOne(['title' => 'Topic 2']);

        $this->tag1 = TagFactory::createOne(['title' => 'Important', 'topic' => $this->tagTopic1->_real()]);
        $this->tag2 = TagFactory::createOne(['title' => 'Urgent', 'topic' => $this->tagTopic1->_real()]);
        $this->tag3 = TagFactory::createOne(['title' => 'Review', 'topic' => $this->tagTopic2->_real()]);
    }

    public function testFilterStatementsByTagIds(): void
    {
        // Arrange: Create statements with segments that have different tags
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement2 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement3 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real());
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment3 */
        $segment3 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement3->_real()])
            ->_real();
        $segment3->addTag($this->tag3->_real());
        $this->getEntityManager()->flush();

        $statements = [$statement1->_real(), $statement2->_real(), $statement3->_real()];

        // Act: Filter by tag1's ID
        $tagsFilter = ['tagIds' => [$this->tag1->getId()]];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: Only statement1 should be included
        static::assertCount(1, $filtered);
        static::assertContains($statement1->_real(), $filtered);
    }

    public function testFilterStatementsByTagTitles(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement2 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real());
        $this->getEntityManager()->flush();

        $statements = [$statement1->_real(), $statement2->_real()];

        // Act: Filter by tag titles
        $tagsFilter = ['tagTitles' => ['Important', 'Urgent']];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: Both statements should be included
        static::assertCount(2, $filtered);
    }

    public function testFilterStatementsByTagTopicIds(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement2 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement3 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real()); // Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real()); // Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment3 */
        $segment3 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement3->_real()])
            ->_real();
        $segment3->addTag($this->tag3->_real()); // Topic 2
        $this->getEntityManager()->flush();

        $statements = [$statement1->_real(), $statement2->_real(), $statement3->_real()];

        // Act: Filter by tagTopic1's ID
        $tagsFilter = ['tagTopicIds' => [$this->tagTopic1->getId()]];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: statement1 and statement2 should be included
        static::assertCount(2, $filtered);
        static::assertContains($statement1->_real(), $filtered);
        static::assertContains($statement2->_real(), $filtered);
    }

    public function testFilterStatementsByTagTopicTitles(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement2 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real()); // Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag3->_real()); // Topic 2
        $this->getEntityManager()->flush();

        $statements = [$statement1->_real(), $statement2->_real()];

        // Act: Filter by topic title
        $tagsFilter = ['tagTopicTitles' => ['Topic 2']];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: Only statement2 should be included
        static::assertCount(1, $filtered);
        static::assertContains($statement2->_real(), $filtered);
    }

    public function testFilterStatementsByMultipleCriteria(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement2 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement3 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real()); // Important, Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement2->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real()); // Urgent, Topic 1
        $this->getEntityManager()->flush();

        /** @var SegmentInterface $segment3 */
        $segment3 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement3->_real()])
            ->_real();
        $segment3->addTag($this->tag3->_real()); // Review, Topic 2
        $this->getEntityManager()->flush();

        $statements = [$statement1->_real(), $statement2->_real(), $statement3->_real()];

        // Act: Filter by tag ID OR topic title (should match statement1 and statement3)
        $tagsFilter = [
            'tagIds'         => [$this->tag1->getId()], // statement1
            'tagTopicTitles' => ['Topic 2'], // statement3
        ];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: statement1 and statement3 should be included
        static::assertCount(2, $filtered);
        static::assertContains($statement1->_real(), $filtered);
        static::assertContains($statement3->_real(), $filtered);
    }

    public function testFilterStatementsWithEmptyFilter(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement2 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        $statements = [$statement1->_real(), $statement2->_real()];

        // Act: Filter with empty array
        $tagsFilter = [];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: All statements should be returned
        static::assertCount(2, $filtered);
    }

    public function testFilterStatementsWithNoMatchingTags(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $this->getEntityManager()->flush();

        $statements = [$statement1->_real()];

        // Act: Filter by non-existent tag ID
        $tagsFilter = ['tagIds' => ['non-existent-id']];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: No statements should be returned
        static::assertCount(0, $filtered);
    }

    public function testFilterStatementsWithMultipleSegmentsPerStatement(): void
    {
        // Arrange: Statement with multiple segments, one has matching tag
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());

        /** @var SegmentInterface $segment2 */
        $segment2 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment2->addTag($this->tag2->_real());

        $this->getEntityManager()->flush();

        $statements = [$statement1->_real()];

        // Act: Filter by tag1
        $tagsFilter = ['tagIds' => [$this->tag1->getId()]];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: Statement should be included because one of its segments matches
        static::assertCount(1, $filtered);
        static::assertContains($statement1->_real(), $filtered);
    }

    public function testFilterStatementsWithSegmentHavingMultipleTags(): void
    {
        // Arrange: Segment with multiple tags
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        /** @var SegmentInterface $segment1 */
        $segment1 = SegmentFactory::createOne(['parentStatementOfSegment' => $statement1->_real()])
            ->_real();
        $segment1->addTag($this->tag1->_real());
        $segment1->addTag($this->tag2->_real());
        $this->getEntityManager()->flush();

        $statements = [$statement1->_real()];

        // Act: Filter by tag2
        $tagsFilter = ['tagIds' => [$this->tag2->getId()]];
        $filtered = $this->invokePrivateMethod('filterStatementsByTags', [$statements, $tagsFilter]);

        // Assert: Statement should be included
        static::assertCount(1, $filtered);
        static::assertContains($statement1->_real(), $filtered);
    }

    /**
     * Helper method to invoke private methods for testing.
     *
     * @param array<int, mixed> $args
     */
    private function invokePrivateMethod(string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($this->sut);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->sut, $args);
    }
}
