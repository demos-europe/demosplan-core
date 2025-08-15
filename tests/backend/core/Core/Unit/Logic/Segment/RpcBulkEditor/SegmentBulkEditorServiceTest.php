<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\Segment\RpcBulkEditor;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSegmentData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentBulkEditorService;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\RpcApiTest;

class SegmentBulkEditorServiceTest extends RpcApiTest
{
    /** @var SegmentBulkEditorService */
    protected $sut;
    private $procedure;
    private $segment1;
    private $segment2;
    private $entityManager;
    private $entityType;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);
        $this->procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $this->segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $this->segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $this->entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $this->entityType = $this->entityManager->getClassMetadata(Segment::class)->getName();
        $this->user = $this->loginTestUser();
        $this->prepareSegments();
    }

    private function prepareSegments(): void
    {
        $em = $this->getEntityManager();
        $this->segment1->setRecommendation('Initial text 1');
        $this->segment2->setRecommendation('Initial text 2');
        $em->persist($this->segment1);
        $em->persist($this->segment2);
        $em->flush();
    }

    public function testUpdateSegmentsWithNotNullAssignee(): void
    {
        self::assertNull($this->segment1->getAssignee());
        self::assertNull($this->segment2->getAssignee());

        $this->sut->updateSegments([$this->segment1, $this->segment2], [], [], $this->user, null, []);

        self::assertEquals($this->user->getId(), $this->segment1->getAssignee()->getId());
        self::assertEquals($this->user->getId(), $this->segment2->getAssignee()->getId());
    }

    public function testUpdateSegmentsCustomFields(): void
    {
        $procedure = ProcedureFactory::createOne();
        $segment1 = SegmentFactory::createOne()->setProcedure($procedure->_real());
        $segment2 = SegmentFactory::createOne()->setProcedure($procedure->_real());

        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1')->create();

        $customField2 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color2')->create();

        $customField1Option1 = $customField1->getConfiguration()->getOptions()[0];
        $customField2Option2 = $customField2->getConfiguration()->getOptions()[1];

        $customFieldsValuesToUpdate = [
            ['id' => $customField1->getId(), 'value' => $customField1Option1->getId()],
            ['id' => $customField2->getId(), 'value' => $customField2Option2->getId()],
        ];

        $this->sut->updateSegments([$segment1, $segment2], [], [], $this->user, null, $customFieldsValuesToUpdate);

        // Get custom field values as arrays for easier assertion
        $segment1Values = array_map(
            static fn ($value) => $value->getValue(),
            $segment1->getCustomFields()->getCustomFieldsValues()
        );

        $segment2Values = array_map(
            static fn ($value) => $value->getValue(),
            $segment2->getCustomFields()->getCustomFieldsValues()
        );

        self::assertContains($customField1Option1->getId(), $segment1Values);
        self::assertContains($customField2Option2->getId(), $segment1Values);
        self::assertContains($customField1Option1->getId(), $segment2Values);
        self::assertContains($customField2Option2->getId(), $segment2Values);
    }

    public function testUpdateSegmentsWithNullAssignee(): void
    {
        $this->segment1->setAssignee($this->user);
        $this->segment2->setAssignee($this->user);

        self::assertEquals($this->user->getId(), $this->segment1->getAssignee()->getId());
        self::assertEquals($this->user->getId(), $this->segment2->getAssignee()->getId());

        $this->sut->updateSegments([$this->segment1, $this->segment2], [], [], null, null, []);

        self::assertNull($this->segment1->getAssignee());
        self::assertNull($this->segment2->getAssignee());
    }

    public function testDetectValidAssignee(): void
    {
        $user = $this->loginTestUser();
        $assignee = $this->sut->detectAssignee($user->getId());
        self::assertEquals($user->getId(), $assignee->getId());
    }

    public function testDetectInvalidIdAssignee(): void
    {
        self::expectException(UserNotFoundException::class);
        $invalidAssigneeId = '123';
        $this->sut->detectAssignee($invalidAssigneeId);
    }

    public function testDetectNullAssignee(): void
    {
        $assignee = $this->sut->detectAssignee(null);
        self::assertNull($assignee);
    }

    public function testGetValidSegments()
    {
        /** @var Procedure $procedure */
        $procedure = ProcedureFactory::createOne()->_real();
        $segmentOne = SegmentFactory::createOne();
        $segmentTwo = SegmentFactory::createOne();

        $segmentOne->setProcedure($procedure);
        $segmentTwo->setProcedure($procedure);
        $segmentOne->_save();
        $segmentTwo->_save();
        /** @var Segment $segmentOneReal */
        $segmentOneReal = $segmentOne->_real();
        /** @var Segment $segmentTwoReal */
        $segmentTwoReal = $segmentTwo->_real();

        $segments = $this->sut->getValidSegments([$segmentOneReal->getId(),  $segmentTwoReal->getId()], $procedure->getId());

        self::assertContains($segmentOneReal, $segments);
        self::assertContains($segmentTwoReal, $segments);
    }

    public function testGetInvalidSegments()
    {
        $invalidProcedureId = '123';
        self::expectException(InvalidArgumentException::class);
        $segments = $this->sut->getValidSegments([$this->segment1->getId(), $this->segment2->getId()], $invalidProcedureId);
        self::assertNotContains($this->segment1, $segments);
        self::assertNotContains($this->segment2, $segments);
    }

    public function testGetValidTags(): void
    {
        $procedure = ProcedureFactory::createOne();
        $tagTopic = TagTopicFactory::createOne();

        $tagTopic->setProcedure($procedure->_real());
        $tagTopic->_save();

        $tag1 = TagFactory::createOne([
            'title' => 'Unique Tag Title 1',
            'topic' => $tagTopic->_real(),
        ]);
        $tag2 = TagFactory::createOne([
            'title' => 'Unique Tag Title 2',
            'topic' => $tagTopic->_real(),
        ]);

        $procedure->addTagTopic($tagTopic->_real());
        $procedure->_save();
        /** @var Procedure $procedureReal */
        $procedureReal = $procedure->_real();

        /** @var Tag $tag1Real */
        $tag1Real = $tag1->_real();
        /** @var Tag $tag2Real */
        $tag2Real = $tag2->_real();

        $tags = $this->sut->getValidTags([$tag1Real->getId(), $tag2Real->getId()], $procedureReal->getId());

        self::assertContains($tag1Real, $tags);
        self::assertContains($tag2Real, $tags);
    }

    public function testGetInvalidTags(): void
    {
        $testTag1 = $this->getTagReference('testFixtureTag_1');
        $testTag2 = $this->getTagReference('testFixtureTag_2');

        self::expectException(InvalidArgumentException::class);
        $tags = $this->sut->getValidTags([$testTag1, $testTag2], $this->procedure->getId());
        self::assertNotContains($testTag1, $tags);
        self::assertNotContains($testTag2, $tags);
    }

    /**
     * @dataProvider recommendationUpdateProvider
     */
    public function testRecommendationUpdate($attach, $expectedResultSegment1, $expectedResultSegment2): void
    {
        static::markSkippedForCIIntervention();
        /** @var Procedure $procedure */
        $procedure = ProcedureFactory::createOne()->_real();
        $segmentOne = SegmentFactory::createOne();
        $segmentTwo = SegmentFactory::createOne();
        $segmentOne->setProcedure($procedure);
        $segmentTwo->setProcedure($procedure);
        $segmentOne->setRecommendation('Initial text 1');
        $segmentTwo->setRecommendation('Initial text 2');
        $segmentOne->_save();
        $segmentTwo->_save();
        /** @var Segment $segmentOneReal */
        $segmentOneReal = $segmentOne->_real();
        /** @var Segment $segmentTwoReal */
        $segmentTwoReal = $segmentTwo->_real();
        $methodCallTime = new DateTime();
        $recommendationTextEdit = (object) [
            'text'   => 'My Text',
            'attach' => $attach,
        ];

        $this->sut->updateRecommendations(
            [$segmentOneReal, $segmentTwoReal],
            $recommendationTextEdit,
            $procedure->getId(),
            Segment::class,
            $methodCallTime
        );

        self::assertSame($expectedResultSegment1, $segmentOneReal->getRecommendation(), 'Segment 1 recommendation did not update as expected');
        self::assertSame($expectedResultSegment2, $segmentTwoReal->getRecommendation(), 'Segment 2 recommendation did not update as expected');
    }

    public function recommendationUpdateProvider(): array
    {
        return [
            'Without Attachment' => [false, 'My Text', 'My Text'],
            'With Attachment'    => [true, 'Initial text 1My Text', 'Initial text 2My Text'],
        ];
    }
}
