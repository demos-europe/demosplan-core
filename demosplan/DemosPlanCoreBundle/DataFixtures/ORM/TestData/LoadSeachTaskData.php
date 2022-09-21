<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\SearchIndexTask;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LoadSeachTaskData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $searchIndexTaskStatement = new SearchIndexTask(
            Statement::class,
            $this->getReference('testStatement1')->getId()
        );
        $manager->persist($searchIndexTaskStatement);

        $searchIndexTaskStatementFragment = new SearchIndexTask(
            StatementFragment::class,
            $this->getReference('testStatementFragment1')->getId()
        );
        $manager->persist($searchIndexTaskStatementFragment);
        $manager->flush();

        $this->setReference('searchIndexTaskStatement', $searchIndexTaskStatement);
        $this->setReference('searchIndexTaskStatementFragment', $searchIndexTaskStatementFragment);
    }

    public function getDependencies()
    {
        return [
            LoadStatementData::class,
        ];
    }
}
