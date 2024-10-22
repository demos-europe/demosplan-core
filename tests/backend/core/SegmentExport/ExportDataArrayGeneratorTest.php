<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\SegmentExport;

use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementMetaInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ExportDataArrayGenerator;
use ReflectionException;
use Tests\Base\FunctionalTestCase;

class ExportDataArrayGeneratorTest extends FunctionalTestCase
{
    /** @var ExportDataArrayGenerator */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ExportDataArrayGenerator::class);
    }

    /**
     * @throws ReflectionException
     */
    public function testConvertIntoExportableArray(): void
    {
        // for test with statement
        /** @var StatementInterface $statement1 */
        $statement1 = StatementFactory::createOne()->_real();
        /** @var StatementMetaInterface $statementMeta */
        $statementMeta1 = StatementMetaFactory::createOne()->_real();
        $statement1->setMeta($statementMeta1);

        // for test with segment
        /** @var StatementInterface $statement2 */
        $statement2 = StatementFactory::createOne()->_real();
        /** @var StatementMetaInterface $statementMeta */
        $statementMeta2 = StatementMetaFactory::createOne()->_real();
        $statement2->setMeta($statementMeta2);
        /** @var PlaceInterface $segment */
        $place = PlaceFactory::createOne()->_real();
        /** @var SegmentInterface $segment */
        $segment = SegmentFactory::createOne()->_real();
        $segment->setParentStatementOfSegment($statement2);
        $segment->setPlace($place);

        $statement1 = $this->createTagData($statement1);
        $segment = $this->createTagData($segment);

        $result1 = $this->sut->convertIntoExportableArray($statement1);
        $result2 = $this->sut->convertIntoExportableArray($segment);

        $this->assertResult($result1, $statement1);
        $this->assertResult($result2, $segment);
    }

    private function assertResult(array $result, StatementInterface|SegmentInterface $statementOrSegment): void
    {
        static::assertArrayHasKey('countyNames', $result);
        static::assertSame($statementOrSegment->getCountyNames(), $result['countyNames']);

        static::assertArrayHasKey('meta', $result);

        if ($statementOrSegment instanceof SegmentInterface) {
            $this->assertMeta($result['meta'], $statementOrSegment->getParentStatementOfSegment());
            $this->assertDataThatInCaseOfSegmentIsTakenFromParentStatement(
                $result,
                $statementOrSegment->getParentStatementOfSegment()
            );
            static::assertArrayHasKey('fileNames', $result);
            static::assertSame($statementOrSegment->getFileNames(), $result['fileNames']);
            static::assertArrayHasKey('status', $result);
            static::assertSame($statementOrSegment->getPlace()->getName(), $result['status']);
        } else {
            $this->assertMeta($result['meta'], $statementOrSegment);
            $this->assertDataThatInCaseOfSegmentIsTakenFromParentStatement($result, $statementOrSegment);
            static::assertArrayHasKey('files', $result);
            static::assertSame($statementOrSegment->getFileNames(), $result['files']);
            static::assertArrayHasKey('status', $result);
            static::assertSame($statementOrSegment->getStatus(), $result['status']);
        }

        static::assertArrayHasKey('tags', $result);
        foreach ($result['tags'] as $key => $tag) {
            static::assertArrayHasKey('topic', $tag);
            /** @var TagTopic $tagTopic */
            $tagTopic = $tag['topic'];
            static::assertInstanceOf(TagTopic::class, $tagTopic);
            static::assertArrayHasKey('topicTitle', $result['tags'][$key]);
            static::assertSame($tagTopic->getTitle(), $result['tags'][$key]['topicTitle']);
        }
        static::assertArrayHasKey('tagNames', $result);
        static::assertSame($statementOrSegment->getTagNames(), $result['tagNames']);
        static::assertArrayHasKey('topicNames', $result);
        static::assertSame($statementOrSegment->getTopicNames(), $result['topicNames']);

        static::assertArrayHasKey('isClusterStatement', $result);
        static::assertSame($statementOrSegment->isClusterStatement(), $result['isClusterStatement']);
    }

    private function assertMeta(array $result, StatementInterface $statement): void
    {
        static::assertArrayHasKey('orgaCity', $result);
        static::assertSame($statement->getOrgaCity(), $result['orgaCity']);
        static::assertArrayHasKey('orgaStreet', $result);
        static::assertSame($statement->getOrgaStreet(), $result['orgaStreet']);
        static::assertArrayHasKey('orgaPostalCode', $result);
        static::assertSame($statement->getOrgaPostalCode(), $result['orgaPostalCode']);
        static::assertArrayHasKey('orgaEmail', $result);
        static::assertSame($statement->getOrgaEmail(), $result['orgaEmail']);
        static::assertArrayHasKey('authorName', $result);
        static::assertSame($statement->getAuthorName(), $result['authorName']);
        static::assertArrayHasKey('houseNumber', $result);
        static::assertArrayHasKey('submitName', $result);
        static::assertSame($statement->getSubmitterName(), $result['submitName']);
        static::assertSame($statement->getMeta()->getHouseNumber(), $result['houseNumber']);
        static::assertArrayHasKey('authoredDate', $result);
        static::assertSame($statement->getAuthoredDateString(), $result['authoredDate']);
    }

    private function assertDataThatInCaseOfSegmentIsTakenFromParentStatement(
        array $result,
        StatementInterface $statement,
    ): void {
        static::assertArrayHasKey('memo', $result);
        static::assertSame($statement->getMemo(), $result['memo']);
        static::assertArrayHasKey('internId', $result);
        static::assertSame($statement->getInternId(), $result['internId']);
        static::assertArrayHasKey('oName', $result);
        static::assertSame($statement->getOName(), $result['oName']);
        static::assertArrayHasKey('dName', $result);
        static::assertSame($statement->getDName(), $result['dName']);
        static::assertArrayHasKey('submitDateString', $result);
        static::assertSame($statement->getSubmitDateString(), $result['submitDateString']);
    }

    private function createTagData(
        StatementInterface|SegmentInterface $statementOrSegment,
    ): StatementInterface|SegmentInterface {
        /** @var TagTopic $tagTopic1 */
        $tagTopic1 = TagTopicFactory::createOne()->_real();
        /** @var TagTopic $tagTopic2 */
        $tagTopic2 = TagTopicFactory::createOne()->_real();
        /** @var Tag $tag1 */
        $tag1 = TagFactory::createOne()->_real();
        $tag1->setTopic($tagTopic1);
        /** @var Tag $tag2 */
        $tag2 = TagFactory::createOne()->_real();
        $tag2->setTopic($tagTopic1);
        /** @var Tag $tag3 */
        $tag3 = TagFactory::createOne()->_real();
        $tag3->setTopic($tagTopic2);
        /** @var Tag $tag4 */
        $tag4 = TagFactory::createOne()->_real();
        $tag4->setTopic($tagTopic2);

        $statementOrSegment->addTags([$tag1, $tag2, $tag3, $tag4]);

        return $statementOrSegment;
    }
}
