<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\StatementSegment;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Tests\Base\AbstractApiTest;

/**
 * Filters of GET /api/3.0/StatementSegment (exact, partial and the custom `assigneeOrUnassigned`).
 */
class StatementSegmentFilterApiTest extends AbstractApiTest
{
    use StatementSegmentApiTestTrait;

    public function testGetCollectionFiltersByAssignee(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $assignee = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $other = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $assigned = $this->createSegmentInProcedure($procedure, ['assignee' => $assignee]);
        $this->createSegmentInProcedure($procedure, ['assignee' => $other]);
        $this->createSegmentInProcedure($procedure);

        $ids = $this->requestCollectionIds('assignee.id='.$assignee->getId(), $procedure);

        self::assertSame([$assigned->getId()], $ids);
    }

    public function testGetCollectionFiltersByExistsAssigneeFalse(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $assignee = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $assigned = $this->createSegmentInProcedure($procedure, ['assignee' => $assignee]);
        $unassigned = $this->createSegmentInProcedure($procedure);

        $unassignedIds = $this->requestCollectionIds('exists[assignee]=false', $procedure);
        $assignedIds = $this->requestCollectionIds('exists[assignee]=true', $procedure);

        self::assertSame([$unassigned->getId()], $unassignedIds);
        self::assertSame([$assigned->getId()], $assignedIds);
    }

    public function testGetCollectionFiltersByPlace(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $place = $this->createPlace($procedure);
        $inPlace = $this->createSegmentInProcedure($procedure, ['place' => $place]);
        $this->createSegmentInProcedure($procedure, ['place' => $this->createPlace($procedure)]);

        $ids = $this->requestCollectionIds('place.id='.$place->getId(), $procedure);

        self::assertSame([$inPlace->getId()], $ids);
    }

    public function testGetCollectionFiltersByTag(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tag = TagFactory::createOne(['topic' => $topic])->_real();
        $tagged = $this->createSegmentInProcedure($procedure, ['tags' => [$tag]]);
        $this->createSegmentInProcedure($procedure);

        $ids = $this->requestCollectionIds('tags.id='.$tag->getId(), $procedure);

        self::assertSame([$tagged->getId()], $ids);
    }

    public function testGetCollectionFiltersByTextPartialCaseInsensitive(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $match = $this->createSegmentInProcedure($procedure, ['text' => 'Laermschutzwand an der Hauptstrasse']);
        $this->createSegmentInProcedure($procedure, ['text' => 'Radweg entlang der Bahnlinie']);

        $ids = $this->requestCollectionIds('text=LAERMSCHUTZ', $procedure);

        self::assertSame([$match->getId()], $ids);
    }

    public function testGetCollectionFiltersByIds(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $first = $this->createSegmentInProcedure($procedure);
        $second = $this->createSegmentInProcedure($procedure);
        $this->createSegmentInProcedure($procedure);

        $ids = $this->requestCollectionIds('id[]='.$first->getId().'&id[]='.$second->getId(), $procedure);

        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $ids);
    }

    public function testGetCollectionAssigneeOrUnassignedReturnsUserAndUnassigned(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $me = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $other = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $mine = $this->createSegmentInProcedure($procedure, ['assignee' => $me]);
        $unassigned = $this->createSegmentInProcedure($procedure);
        $others = $this->createSegmentInProcedure($procedure, ['assignee' => $other]);

        $ids = $this->requestCollectionIds(
            'assigneeOrUnassigned[]='.$me->getId().'&assigneeOrUnassigned[]=',
            $procedure
        );

        self::assertEqualsCanonicalizing([$mine->getId(), $unassigned->getId()], $ids);
        self::assertNotContains($others->getId(), $ids);
    }

    public function testGetCollectionAssigneeOrUnassignedWithOnlyEmptyValueReturnsUnassigned(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $me = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->createSegmentInProcedure($procedure, ['assignee' => $me]);
        $unassigned = $this->createSegmentInProcedure($procedure);

        $ids = $this->requestCollectionIds('assigneeOrUnassigned[]=', $procedure);

        self::assertSame([$unassigned->getId()], $ids);
    }

    public function testGetCollectionAssigneeOrUnassignedWithSeveralUsers(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $me = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $other = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $mine = $this->createSegmentInProcedure($procedure, ['assignee' => $me]);
        $others = $this->createSegmentInProcedure($procedure, ['assignee' => $other]);
        $unassigned = $this->createSegmentInProcedure($procedure);

        $ids = $this->requestCollectionIds(
            'assigneeOrUnassigned[]='.$me->getId().'&assigneeOrUnassigned[]='.$other->getId(),
            $procedure
        );

        self::assertEqualsCanonicalizing([$mine->getId(), $others->getId()], $ids);
        self::assertNotContains($unassigned->getId(), $ids);
    }

    public function testGetCollectionCombinesFiltersWithAnd(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $me = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $place = $this->createPlace($procedure);
        $matchesBoth = $this->createSegmentInProcedure($procedure, ['assignee' => $me, 'place' => $place]);
        $this->createSegmentInProcedure($procedure, ['assignee' => $me, 'place' => $this->createPlace($procedure)]);
        $this->createSegmentInProcedure($procedure, ['place' => $place]);

        $ids = $this->requestCollectionIds(
            'assigneeOrUnassigned[]='.$me->getId().'&place.id='.$place->getId(),
            $procedure
        );

        self::assertSame([$matchesBoth->getId()], $ids);
    }

    /**
     * The procedure filter the frontend sends must not widen access: asking for another
     * procedure while working in the current one yields nothing, not the other procedure's segments.
     */
    public function testGetCollectionFilterForOtherProcedureReturnsNothing(): void
    {
        $currentProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createSegmentInProcedure($currentProcedure);
        $this->createSegmentInProcedure($otherProcedure);

        $ids = $this->requestCollectionIds(
            'parentStatementOfSegment.procedure.id='.$otherProcedure->getId(),
            $currentProcedure
        );

        self::assertSame([], $ids);
    }

    private function createPlace(Procedure $procedure): Place
    {
        return PlaceFactory::createOne(['procedure' => $procedure])->_real();
    }
}
