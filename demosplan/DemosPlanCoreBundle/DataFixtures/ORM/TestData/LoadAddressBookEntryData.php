<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LoadAddressBookEntryData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $testOrganisation = $this->getReference('testOrgaFP');

        $addressBookEntry1 = new AddressBookEntry('Heinz', 'Heinzilein@fantasy.com', $testOrganisation);
        $manager->persist($addressBookEntry1);
        $this->setReference('testAddressBookEntry1', $addressBookEntry1);

        $addressBookEntry2 = new AddressBookEntry('Paule', 'Paulemann@fantasy.com', $testOrganisation);
        $manager->persist($addressBookEntry2);
        $this->setReference('testAddressBookEntry2', $addressBookEntry2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
          LoadUserData::class,
        ];
    }
}
