<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadReportData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Customer $customer */
        $customer = $this->getReference('Brandenburg');

        $reportEntry = new ReportEntry();
        $reportEntry->setIdentifier('testingIdentifier4-000f-123456789d98');
        $reportEntry->setCategory('add');
        $reportEntry->setGroup('procedure');
        $reportEntry->setIncoming('incommingData');
        $reportEntry->setIdentifierType('test');
        $reportEntry->setMessage('message');
        $reportEntry->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $reportEntry->setCustomer($customer);

        $manager->persist($reportEntry);
        $this->setReference('testReportEntry1', $reportEntry);

        $reportEntry2 = new ReportEntry();
        $reportEntry2->setIdentifier('testingIdentifier4-000f-123456789d98');
        $reportEntry2->setCategory('add');
        $reportEntry2->setGroup('procedure');
        $reportEntry2->setIncoming('incommingData2');
        $reportEntry2->setIdentifierType('test2');
        $reportEntry2->setMessage('message2');
        $reportEntry2->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $reportEntry2->setCustomer($customer);

        $manager->persist($reportEntry2);
        $this->setReference('testReportEntry2', $reportEntry2);

        $reportEntry3 = new ReportEntry();
        $reportEntry3->setIdentifier('testingIdentifier4-444f-123456789d98');
        $reportEntry3->setCategory('update');
        $reportEntry3->setGroup('mastertoeb');
        $reportEntry3->setIncoming('incommingData3');
        $reportEntry3->setIdentifierType('masterToeb');
        $reportEntry3->setMessage('message3');
        $reportEntry3->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $reportEntry3->setCustomer($customer);

        $manager->persist($reportEntry3);
        $this->setReference('testReportEntry3', $reportEntry3);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadCustomerData::class,
        ];
    }
}
