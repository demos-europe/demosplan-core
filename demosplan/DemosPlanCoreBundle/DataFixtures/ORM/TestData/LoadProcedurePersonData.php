<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedurePersonData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $procedurePerson1 = new ProcedurePerson('Max Mustermann', $testProcedure);
        $manager->persist($procedurePerson1);
        $this->setReference('testProcedurePerson1', $procedurePerson1);

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $procedurePerson2 = new ProcedurePerson('Malia Musterfrau', $testProcedure);
        $manager->persist($procedurePerson2);
        $this->setReference('testProcedurePerson2', $procedurePerson2);

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $procedurePerson3 = new ProcedurePerson('Oliver Groß', $testProcedure);
        $manager->persist($procedurePerson3);
        $this->setReference('testprocedurePerson3', $procedurePerson3);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }
}
