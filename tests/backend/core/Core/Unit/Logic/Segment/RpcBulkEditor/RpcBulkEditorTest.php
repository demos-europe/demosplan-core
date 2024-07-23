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
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSegmentData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor\RpcSegmentsBulkEditor;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentBulkEditorService;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\RpcApiTest;

class RpcBulkEditorTest extends RpcApiTest
{
    /** @var RpcSegmentsBulkEditor */
    protected $sut;
    private $procedure;
    private $segment1;
    private $segment2;
    private $entityManager;
    private $entityType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);
        $this->procedure = $this->getProcedureReference(\demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData::TESTPROCEDURE);
        $this->segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $this->segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $this->entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $this->entityType = $this->entityManager->getClassMetadata(Segment::class)->getName();
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
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $user = $this->loginTestUser();

        static::assertNull($segment1->getAssignee());
        static::assertNull($segment2->getAssignee());

        $this->sut->updateSegments([$segment1, $segment2], [], [], $user, null);

        static::assertEquals($user->getId(), $segment1->getAssignee()->getId());
        static::assertEquals($user->getId(), $segment2->getAssignee()->getId());
    }

    public function testUpdateSegmentsWithNullAssignee(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $user = $this->loginTestUser();
        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment1->setAssignee($user);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $segment2->setAssignee($user);

        static::assertEquals($user->getId(), $segment1->getAssignee()->getId());
        static::assertEquals($user->getId(), $segment2->getAssignee()->getId());

        $this->sut->updateSegments([$segment1, $segment2], [], [], null, null);

        static::assertNull($segment1->getAssignee());
        static::assertNull($segment2->getAssignee());
    }

    public function testDetectValidAssignee(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);
        $user = $this->loginTestUser();
        $assignee = $this->sut->detectAssignee($user->getId());
        static::assertEquals($user->getId(), $assignee->getId());
    }

    public function testDetectInvalidIdAssignee(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);
        static::expectException(UserNotFoundException::class);
        $invalidAssigneeId = '123';
        $this->sut->detectAssignee($invalidAssigneeId);
    }

    public function testDetectNullAssignee(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);
        $assignee = $this->sut->detectAssignee(null);
        static::assertNull($assignee);
    }

    public function testGetValidSegments()
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $procedure = $this->getProcedureReference(\demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData::TESTPROCEDURE);
        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $segments = $this->sut->getValidSegments([$segment1->getId(), $segment2->getId()], $procedure->getId());

        static::assertContains($segment1, $segments);
        static::assertContains($segment2, $segments);
    }

    public function testGetInvalidSegments()
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $invalidProcedureId = '123';
        static::expectException(InvalidArgumentException::class);
        $segments = $this->sut->getValidSegments([$segment1->getId(), $segment2->getId()], $invalidProcedureId);
    }

    public function testGetValidTags(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $procedure = $this->getProcedureReference(\demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData::TESTPROCEDURE);
        $tag1 = $procedure->getTags()->get(0);
        $tag2 = $procedure->getTags()->get(1);
        $tags = $this->sut->getValidTags([$tag1, $tag2], $procedure->getId());

        static::assertContains($tag1, $tags);
        static::assertContains($tag2, $tags);
    }

    public function testGetInvalidTags(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $testTag1 = $this->getTagReference('testFixtureTag_1');
        $testTag2 = $this->getTagReference('testFixtureTag_2');
        $procedure = $this->getProcedureReference(\demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData::TESTPROCEDURE);

        static::expectException(InvalidArgumentException::class);
        $tags = $this->sut->getValidTags([$testTag1, $testTag2], $procedure->getId());
    }

    /**
     * @dataProvider recommendationUpdateProvider
     */
    public function testRecommendationUpdate($attach, $expectedResultSegment1, $expectedResultSegment2): void
    {
        $methodCallTime = new DateTime();
        $recommendationTextEdit = (object) [
            'text'   => 'My Text',
            'attach' => $attach,
        ];

        $this->sut->updateRecommendations([$this->segment1, $this->segment2], $recommendationTextEdit, $this->procedure->getId(), $this->entityType, $methodCallTime);

        static::assertSame($expectedResultSegment1, $this->segment1->getRecommendation(), 'Segment 1 recommendation did not update as expected');
        static::assertSame($expectedResultSegment2, $this->segment2->getRecommendation(), 'Segment 2 recommendation did not update as expected');
    }

    public function recommendationUpdateProvider(): array
    {
        return [
            'Without Attachment' => [false, 'My Text', 'My Text'],
            'With Attachment'    => [true, 'Initial text 1My Text', 'Initial text 2My Text'],
        ];
    }
}
