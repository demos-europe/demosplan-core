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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
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

        $this->sut->updateSegments([$this->segment1, $this->segment2], [], [], $this->user, null);

        self::assertEquals($this->user->getId(), $this->segment1->getAssignee()->getId());
        self::assertEquals($this->user->getId(), $this->segment2->getAssignee()->getId());
    }

    public function testUpdateSegmentsWithNullAssignee(): void
    {
        $this->segment1->setAssignee($this->user);
        $this->segment2->setAssignee($this->user);

        self::assertEquals($this->user->getId(), $this->segment1->getAssignee()->getId());
        self::assertEquals($this->user->getId(), $this->segment2->getAssignee()->getId());

        $this->sut->updateSegments([$this->segment1, $this->segment2], [], [], null, null);

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
        $segments = $this->sut->getValidSegments([$this->segment1->getId(),  $this->segment2->getId()], $this->procedure->getId());

        self::assertContains($this->segment1, $segments);
        self::assertContains($this->segment2, $segments);
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
        $tag1 = $this->procedure->getTags()->get(0);
        $tag2 = $this->procedure->getTags()->get(1);
        $tags = $this->sut->getValidTags([$tag1, $tag2], $this->procedure->getId());

        self::assertContains($tag1, $tags);
        self::assertContains($tag2, $tags);
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
        $methodCallTime = new DateTime();
        $recommendationTextEdit = (object) [
            'text'   => 'My Text',
            'attach' => $attach,
        ];

        $this->sut->updateRecommendations([$this->segment1, $this->segment2], $recommendationTextEdit, $this->procedure->getId(), $this->entityType, $methodCallTime);

        self::assertSame($expectedResultSegment1, $this->segment1->getRecommendation(), 'Segment 1 recommendation did not update as expected');
        self::assertSame($expectedResultSegment2, $this->segment2->getRecommendation(), 'Segment 2 recommendation did not update as expected');
    }

    public function recommendationUpdateProvider(): array
    {
        return [
            'Without Attachment' => [false, 'My Text', 'My Text'],
            'With Attachment'    => [true, 'Initial text 1My Text', 'Initial text 2My Text'],
        ];
    }
}
