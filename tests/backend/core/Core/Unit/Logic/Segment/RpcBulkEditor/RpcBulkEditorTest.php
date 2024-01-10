<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\Segment\RpcBulkEditor;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSegmentData;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor\RpcSegmentsBulkEditor;
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

        $this->sut = $this->getContainer()->get(RpcSegmentsBulkEditor::class);


        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $user = $this->loginTestUser();

        static::assertNull($segment1->getAssignee());
        static::assertNull($segment2->getAssignee());

        $this->sut->updateSegments(array($segment1, $segment2), array(), array(), $user, null);

        static::assertEquals($user->getId(), $segment1->getAssignee()->getId());
        static::assertEquals($user->getId(), $segment2->getAssignee()->getId());

    }

    public function testUpdateSegmentsWithNullAssignee(): void
    {

        $this->sut = $this->getContainer()->get(RpcSegmentsBulkEditor::class);

        $user = $this->loginTestUser();
        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment1->setAssignee($user);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $segment2->setAssignee($user);

        static::assertEquals($user->getId(), $segment1->getAssignee()->getId());
        static::assertEquals($user->getId(), $segment2->getAssignee()->getId());

        $this->sut->updateSegments(array($segment1, $segment2), array(), array(), null, null);

        static::assertNull($segment1->getAssignee());
        static::assertNull($segment2->getAssignee());

    }

    public function testDetectValidAssignee(): void {
        $this->sut = $this->getContainer()->get(RpcSegmentsBulkEditor::class);

        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);
        $user = $this->loginTestUser();

        static::assertNull($segment1->getAssignee());
        static::assertNull($segment2->getAssignee());

        $rpcRequest = (object)[
            "jsonrpc" => "2.0",
            "method" => "segment.bulk.edit",
            "id" => "someId",
            "params" => [
                "addTagIds" => [],
                "removeTagIds" => [],
                "segmentIds" => [
                    $segment1->getId(),
                    $segment2->getId(),
                ],
                "recommendationTextEdit" => [
                    "text" => "",
                    "attach" => true
                ],
                "assigneeId" => $user->getId()
            ]
        ];

        $assignee = $this->sut->detectAssignee($rpcRequest);

        static::assertEquals($user->getId(),$assignee->getId());


    }

    public function testDetectInvalidIdAssignee(): void {
        $this->sut = $this->getContainer()->get(RpcSegmentsBulkEditor::class);

        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);


        static::assertNull($segment1->getAssignee());
        static::assertNull($segment2->getAssignee());

        static::expectException(UserNotFoundException::class);

        $rpcRequest = (object)[
            "jsonrpc" => "2.0",
            "method" => "segment.bulk.edit",
            "id" => "someId",
            "params" => [
                "addTagIds" => [],
                "removeTagIds" => [],
                "segmentIds" => [
                    $segment1->getId(),
                    $segment2->getId(),
                ],
                "recommendationTextEdit" => [
                    "text" => "",
                    "attach" => true
                ],
                "assigneeId" => "134"
            ]
        ];

        $this->sut->detectAssignee($rpcRequest);

    }

    public function testDetectNullAssignee(): void {
        $this->sut = $this->getContainer()->get(RpcSegmentsBulkEditor::class);

        $segment1 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_1);
        $segment2 = $this->getSegmentReference(LoadSegmentData::SEGMENT_BULK_EDIT_2);


        static::assertNull($segment1->getAssignee());
        static::assertNull($segment2->getAssignee());

        $rpcRequest = (object)[
            "jsonrpc" => "2.0",
            "method" => "segment.bulk.edit",
            "id" => "someId",
            "params" => [
                "addTagIds" => [],
                "removeTagIds" => [],
                "segmentIds" => [
                    $segment1->getId(),
                    $segment2->getId(),
                ],
                "recommendationTextEdit" => [
                    "text" => "",
                    "attach" => true
                ],
                "assigneeId" => null
            ]
        ];

        $assignee = $this->sut->detectAssignee($rpcRequest);

        static::assertNull($assignee);

    }



}
