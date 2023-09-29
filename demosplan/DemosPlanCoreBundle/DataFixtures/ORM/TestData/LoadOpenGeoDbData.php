<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\OpenGeoDbShortTable;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadOpenGeoDbData extends TestFixture
{
    public function load(ObjectManager $manager): void
    {
        $openGeoDbEntry = new OpenGeoDbShortTable();
        $openGeoDbEntry
            ->setId('1')
            ->setCity('Berlin')
            ->setPostcode('10178')
            ->setState('Berlin')
            ->setMunicipalCode('11000000')
            ->setLat('52.52468')
            ->setLon('13.40535');

        $manager->persist($openGeoDbEntry);
        $this->setReference('testOpenGeoDbEntry', $openGeoDbEntry);

        $manager->flush();
    }
}
