<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedureSubscriptionData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $procedureId = $this->getReference('testProcedure2')->getId();

        $subscription1 = new ProcedureSubscription();
        $subscription1->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $subscription1->setPostcode('11111');
        $subscription1->setCity('Berlin');
        $subscription1->setDistance('2');
        $subscription1->setCreatedDate(new DateTime());
        $subscription1->setModifiedDate(new DateTime());
        $subscription1->setDeleted(false);

        $this->setReference('testProcedureSubscription1', $subscription1);
        $manager->persist($subscription1);

        $subscription2 = new ProcedureSubscription();
        $subscription2->setUser($this->getReference('testUserPlanningOffice'));
        $subscription2->setPostcode('22222');
        $subscription2->setCity('Hamburg');
        $subscription2->setDistance('5');
        $subscription2->setCreatedDate(new DateTime());
        $subscription2->setModifiedDate(new DateTime());
        $subscription2->setDeleted(false);

        $this->setReference('testProcedureSubscription2', $subscription2);
        $manager->persist($subscription2);
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
            LoadUserData::class,
        ];
    }
}
