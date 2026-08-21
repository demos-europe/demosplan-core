<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use Tests\Base\FunctionalTestCase;

/**
 * Regression guard for the `sentViaMail` pseudo-field skip in the auto-diff
 * tracker (EntityContentChangeService::calculateChanges*).
 *
 * `sentViaMail` is registered in entity_content_change_fields_mapping.yml so
 * it renders in the Versionsverlauf, but it is NOT a real Segment property —
 * it is written explicitly by createSegmentSentByMailChangeEntry(). The tracker
 * iterates the whole field mapping on every segment edit, so without the skip
 * it would fatal trying to read a non-existent getter. This is exactly the
 * crash that broke "divide statement into segments".
 */
class SegmentSentViaMailAutoDiffSkipTest extends FunctionalTestCase
{
    protected ?EntityContentChangeService $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(EntityContentChangeService::class);

        // determineChanger() reads the current user.
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN));
    }

    public function testTrackChangesOnNormalFieldSkipsSentViaMailWithoutError(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $segment->setRecommendation('original recommendation');
        $this->getEntityManager()->flush();

        $beforeRecommendation = $this->countEntriesFor($segment, 'recommendation');
        $beforeSentViaMail = $this->countEntriesFor($segment, 'sentViaMail');

        // Edit a real tracked field. The auto-diff loop iterates the whole field
        // mapping (which now includes `sentViaMail`); if that synthetic field were
        // not skipped, trackChanges would fatal on a missing getter.
        $segment->setRecommendation('changed recommendation');
        $this->sut->trackChanges($segment, Segment::class);
        $this->getEntityManager()->flush();

        self::assertSame(
            $beforeRecommendation + 1,
            $this->countEntriesFor($segment, 'recommendation'),
            'Editing recommendation must still produce a `recommendation` audit row (loop ran past sentViaMail)',
        );
        self::assertSame(
            $beforeSentViaMail,
            $this->countEntriesFor($segment, 'sentViaMail'),
            'A normal field edit must not auto-create a `sentViaMail` row',
        );
    }

    private function countEntriesFor(Segment $segment, string $entityField): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                'SELECT COUNT(*) FROM entity_content_change WHERE entity_id = :id AND entity_field = :field',
                ['id' => $segment->getId(), 'field' => $entityField],
            )
            ->fetchOne();
    }
}
