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


    public function testUpdateSegments(): void
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
}
