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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentLockEnforcementService;
use demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository;
use Tests\Base\FunctionalTestCase;

/**
 * Functional test for the two audit-emission entry points on
 * EntityContentChangeService added for the segment-lock feature.
 *
 * Uses the real service from the container (so translator, Twig and
 * Doctrine wiring are exercised end-to-end) and verifies rows land in
 * entity_content_change with the expected shape via the repository.
 *
 * Requires parameters_test.yml to set `segment_lock_by_workflow_place: true`
 * — otherwise both methods short-circuit on the feature flag.
 */
class SegmentLockContentChangeEmissionTest extends FunctionalTestCase
{
    protected ?EntityContentChangeService $sut = null;
    private ?EntityContentChangeRepository $entityContentChangeRepository = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(EntityContentChangeService::class);
        $this->entityContentChangeRepository = $this->getContainer()->get(EntityContentChangeRepository::class);

        // determineChanger() reads the current user — log in a test user so
        // the service can attribute the audit entries.
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN));

        // Sanity check: the test env must have the feature enabled, otherwise
        // every emission method short-circuits and the tests silently pass
        // by doing nothing.
        self::assertTrue(
            $this->getContainer()->get(SegmentLockEnforcementService::class)->isFeatureEnabled(),
            'parameters_test.yml must set `segment_lock_by_workflow_place: true` for these tests',
        );
    }

    public function testCreateSegmentLockedChangeEntryOnPlaceChangeIsNoopWhenLockStateUnchanged(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $placeA = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $placeB = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $segment = SegmentFactory::createOne(['place' => $placeA])->_real();

        $before = $this->countLockEntriesFor($segment);

        // Both places unlocked — no state change, no entry.
        $this->sut->createSegmentLockedChangeEntryOnPlaceChange($segment, $placeA, $placeB);
        $this->getEntityManager()->flush();

        self::assertSame($before, $this->countLockEntriesFor($segment));
    }

    public function testCreateSegmentLockedChangeEntryOnPlaceChangeWritesOneRowWhenLockStateFlips(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $unlocked = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $locked = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => true])->_real();
        $segment = SegmentFactory::createOne(['place' => $unlocked])->_real();

        $before = $this->countLockEntriesFor($segment);

        $this->sut->createSegmentLockedChangeEntryOnPlaceChange($segment, $unlocked, $locked);
        $this->getEntityManager()->flush();

        self::assertSame($before + 1, $this->countLockEntriesFor($segment));
    }

    public function testCreateSegmentLockedChangeEntriesForPlaceToggleWritesOneRowPerSegmentOnThatPlace(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $place = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $otherPlace = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();

        $segmentsOnPlace = [
            SegmentFactory::createOne(['place' => $place])->_real(),
            SegmentFactory::createOne(['place' => $place])->_real(),
            SegmentFactory::createOne(['place' => $place])->_real(),
        ];
        // Segment on a different place — must NOT get an entry.
        $segmentElsewhere = SegmentFactory::createOne(['place' => $otherPlace])->_real();

        $beforeOnPlace = array_map(fn ($s) => $this->countLockEntriesFor($s), $segmentsOnPlace);
        $beforeElsewhere = $this->countLockEntriesFor($segmentElsewhere);

        // Toggle the place locked -> true. All three segments should get a row.
        $this->sut->createSegmentLockedChangeEntriesForPlaceToggle($place, false, true);
        $this->getEntityManager()->flush();

        foreach ($segmentsOnPlace as $i => $segment) {
            self::assertSame(
                $beforeOnPlace[$i] + 1,
                $this->countLockEntriesFor($segment),
                sprintf('Segment #%d on toggled place should have exactly one new locked audit row', $i),
            );
        }
        self::assertSame(
            $beforeElsewhere,
            $this->countLockEntriesFor($segmentElsewhere),
            'Segment on a different place must not receive an audit row',
        );
    }

    public function testCreateSegmentLockedChangeEntriesForPlaceToggleIsNoopWhenLockStateUnchanged(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $place = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $segment = SegmentFactory::createOne(['place' => $place])->_real();

        $before = $this->countLockEntriesFor($segment);

        // Same old/new -> no-op regardless of how many segments reference the place.
        $this->sut->createSegmentLockedChangeEntriesForPlaceToggle($place, false, false);
        $this->getEntityManager()->flush();

        self::assertSame($before, $this->countLockEntriesFor($segment));
    }

    public function testAutoDiffProducesAuditRowsForAssigneeAndPlaceWhenBothChangeWithoutLockCrossing(): void
    {
        // The user-reported scenario WITHOUT lock crossing: change place + assignee
        // together where both places are unlocked. Our lock-emission method
        // short-circuits, so trackChanges (auto-diff) is the sole audit path.
        // Both `place` and `assignee` rows must appear.
        $procedure = ProcedureFactory::createOne()->_real();
        $placeA = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $placeB = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $userA = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $userB = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);

        $segment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place'     => $placeA,
            'assignee'  => $userA,
        ])->_real();
        $this->getEntityManager()->flush();

        $beforePlace = $this->countEntriesFor($segment, 'place');
        $beforeAssignee = $this->countEntriesFor($segment, 'assignee');

        $segment->setPlace($placeB);
        $segment->setAssignee($userB);

        $this->sut->trackChanges($segment, Segment::class);
        $this->getEntityManager()->flush();

        self::assertSame(
            $beforePlace + 1,
            $this->countEntriesFor($segment, 'place'),
            'Place change must produce one `place` row (auto-diff)',
        );
        self::assertSame(
            $beforeAssignee + 1,
            $this->countEntriesFor($segment, 'assignee'),
            'Assignee change must produce one `assignee` row (auto-diff)',
        );
    }

    public function testMultiFieldUpdateProducesAuditRowsForEachChangedFieldWhenLockBoundaryCrossed(): void
    {
        // Regression for the bug where a flush inside the lock-emission path
        // committed the segment's pending UoW changes too early, leaving the
        // subsequent auto-diff with no diff to record. After the fix
        // (persistEntities, no flush) the auto-diff still sees the original
        // snapshot and emits a row per changed field.
        $procedure = ProcedureFactory::createOne()->_real();
        $unlockedPlace = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false])->_real();
        $lockedPlace = PlaceFactory::createOne(['procedure' => $procedure, 'locked' => true])->_real();
        $userA = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $userB = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);

        $segment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place'     => $unlockedPlace,
            'assignee'  => $userA,
        ])->_real();
        $this->getEntityManager()->flush();

        $beforeLocked = $this->countEntriesFor($segment, 'locked');
        $beforePlace = $this->countEntriesFor($segment, 'place');
        $beforeAssignee = $this->countEntriesFor($segment, 'assignee');

        // Mimic the JSON:API PATCH flow: mutate the entity in-memory,
        // then run the lock-emission method (priority 100 subscriber path)
        // followed by trackChanges (priority 0 audit-history path).
        $segment->setPlace($lockedPlace);
        $segment->setAssignee($userB);

        $this->sut->createSegmentLockedChangeEntryOnPlaceChange($segment, $unlockedPlace, $lockedPlace);
        $this->sut->trackChanges($segment, Segment::class);

        $this->getEntityManager()->flush();

        self::assertSame(
            $beforeLocked + 1,
            $this->countEntriesFor($segment, 'locked'),
            'Lock change must produce one `locked` row',
        );
        self::assertSame(
            $beforePlace + 1,
            $this->countEntriesFor($segment, 'place'),
            'Place change must produce one `place` row (auto-diff)',
        );
        self::assertSame(
            $beforeAssignee + 1,
            $this->countEntriesFor($segment, 'assignee'),
            'Assignee change must produce one `assignee` row (auto-diff)',
        );
    }

    private function countLockEntriesFor(Segment $segment): int
    {
        return $this->countEntriesFor($segment, 'locked');
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
