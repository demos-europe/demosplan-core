<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadSegmentData extends TestFixture implements DependentFixtureInterface
{
    final public const SEGMENT_BULK_EDIT_1 = 'segmentTestTagsBulkEdit1';
    final public const SEGMENT_BULK_EDIT_2 = 'segmentTestTagsBulkEdit2';
    final public const SEGMENT_WITH_ASSIGNEE = 'segmentWithAssignee';

    protected $manager;

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;
        /** @var Statement $statementTestTagsBulkEdit1 */
        $statementTestTagsBulkEdit1 = $this->getReference('statementTestTagsBulkEdit1');
        $manager->persist(
            $this->createMinimalSegment(
                $statementTestTagsBulkEdit1,
                self::SEGMENT_BULK_EDIT_1,
                ''
            )
        );
        $manager->persist(
            $this->createMinimalSegment(
                $statementTestTagsBulkEdit1,
                self::SEGMENT_BULK_EDIT_2,
                'foobar'
            )
        );

        $segment = $this->createMinimalSegment(
            $statementTestTagsBulkEdit1,
            self::SEGMENT_WITH_ASSIGNEE,
            'foobar'
        );
        /** @var User $assignee */
        $assignee = $this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $segment->setAssignee($assignee);
        $manager->persist($segment);

        $manager->flush();
    }

    private function createMinimalSegment(Statement $statement, string $reference, string $recommendation): Statement
    {
        /** @var Place $place */
        $place = $this->getReference(LoadWorkflowPlaceData::PLACE_REPLY);

        $segment = new Segment();
        $segment->setParentStatementOfSegment($statement);
        $segment->setProcedure($statement->getProcedure());
        $segment->setExternId($reference);
        $segment->setPhase('participation');
        $segment->setPublicVerified(Statement::PUBLICATION_PENDING);
        $segment->setText('Lorem ipsum');
        $segment->setPlace($place);
        $segment->setRecommendation($recommendation);
        $this->setReference($reference, $segment);

        return $segment;
    }

    public function getDependencies()
    {
        return [
            LoadStatementData::class,
        ];
    }
}
