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
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadSettingData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $setting1 = new Setting();
        $setting1->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $setting1->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $setting1->setOrga($this->getReference('testOrgaFP'));
        $setting1->setKey('testkey');
        $setting1->setContent('http://urlstring');

        $manager->persist($setting1);

        $setting2 = new Setting();
        $setting2->setOrga($this->getReference('testOrgaFP'));
        $setting2->setKey('emailNotificationEndingPhase');
        $setting2->setContent('true');

        $manager->persist($setting2);

        $setting3 = new Setting();
        $setting3->setOrga($this->getReference('testOrgaPB'));
        $setting3->setKey('emailNotificationEndingPhase');
        $setting3->setContent('true');

        $manager->persist($setting3);

        $setting5 = new Setting();
        $setting5->setUser($this->getReference('testUserDelete'));
        $setting5->setKey('testkey2');
        $setting5->setContent('true');
        $setting5->setModified(new DateTime('-1 day'));

        $manager->persist($setting5);

        $this->setReference('testSettings', $setting2);
        $this->setReference('testSettingdelete', $setting5);
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
