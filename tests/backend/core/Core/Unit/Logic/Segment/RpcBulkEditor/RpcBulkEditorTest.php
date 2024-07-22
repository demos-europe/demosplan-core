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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
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

    protected function setUp(): void
    {
        parent::setUp();
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

    public function testUpdateRecommendation(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $procedure = $this->getProcedureReference(\demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData::TESTPROCEDURE);
        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment1->setRecommendation('Initial text 1');
        $em = $this->getEntityManager();
        $em->persist($segment1);
        $em->flush();

        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $segment2->setRecommendation('Initial text 2');
        $em = $this->getEntityManager();
        $em->persist($segment2);
        $em->flush();
        // $segment1 = SegmentFactory::createOne();
        // $segment2 = SegmentFactory::createOne();
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityType = $entityManager->getClassMetadata(Segment::class)->getName();
        $methodCallTime = new DateTime();
        $recommendationTextEdit = (object) [
            'text'   => 'My Text',
            'attach' => false,
        ];

        $this->sut->updateRecommendations([$segment1, $segment2], $recommendationTextEdit, $procedure->getId(), $entityType, $methodCallTime);

        static::assertEquals($recommendationTextEdit->text, $segment1->getRecommendation());
        static::assertEquals($recommendationTextEdit->text, $segment2->getRecommendation());
    }

    public function testAttachUpdateRecommendation(): void
    {
        $this->sut = $this->getContainer()->get(SegmentBulkEditorService::class);

        $procedure = $this->getProcedureReference(\demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData::TESTPROCEDURE);
        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment1->setRecommendation('Initial text 1');
        $em = $this->getEntityManager();
        $em->persist($segment1);
        $em->flush();

        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $segment2->setRecommendation('Initial text 2');
        $em = $this->getEntityManager();
        $em->persist($segment2);
        $em->flush();

        $recommendationText1 = $segment1->getRecommendation();
        $recommendationText2 = $segment2->getRecommendation();
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityType = $entityManager->getClassMetadata(Segment::class)->getName();
        $methodCallTime = new DateTime();
        $recommendationTextEdit = (object) [
            'text'   => 'My Text',
            'attach' => true,
        ];

        $this->sut->updateRecommendations([$segment1, $segment2], $recommendationTextEdit, $procedure->getId(), $entityType, $methodCallTime);
        $methodCallTime = new DateTime();
        static::assertEquals($recommendationText1.$recommendationTextEdit->text, $segment1->getRecommendation());
        static::assertEquals($recommendationText2.$recommendationTextEdit->text, $segment2->getRecommendation());
    }
}
