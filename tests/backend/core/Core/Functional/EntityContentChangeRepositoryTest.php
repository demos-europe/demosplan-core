<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSegmentData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class EntityContentChangeRepositoryTest extends FunctionalTestCase
{
    /**
     * @var EntityContentChangeRepository
     */
    protected $sut;

    /**
     * @var Session
     */
    protected $mockSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(EntityContentChangeRepository::class);

        // set permission feature_statement_content_changes_save = true!
        $this->mockSession = $this->setUpMockSession();
    }

    public function testGetEntityAssigneeChangesByEntityAndStartTime(): void
    {
        $segment = $this->getSegmentReference(LoadSegmentData::SEGMENT_WITH_ASSIGNEE);
        $time = Carbon::instance($segment->getCreated())->sub('hour', 12)->toDateTimeString();

        $resultSegments = $this->sut->getEntityAssigneeChangesByEntityAndStartTime(Segment::class, $time);
        self::assertCount(1, $resultSegments);

        $resultSegment = $resultSegments[0];
        self::assertSame($segment->getId(), $resultSegment->getId());
    }
}
