<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadManualListSortData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $procedureNewsIdents = $this->getReference(LoadNewsData::TEST_SINGLE_NEWS_2)->getIdent().', '.$this->getReference(LoadNewsData::TEST_SINGLE_NEWS_1)->getIdent();

        $manualListSort = new ManualListSort();
        $manualListSort->setContext('procedure:'.$this->getReference('testProcedure2')->getId());
        $manualListSort->setPId($this->getReference('testProcedure2')->getId());
        $manualListSort->setNamespace('news');
        $manualListSort->setIdents($procedureNewsIdents);

        $manager->persist($manualListSort);

        $globalNewsIdents = $this->getReference('testGlobalNews2')->getIdent().','.$this->getReference('testGlobalNews1')->getIdent();

        $manualListSort2 = new ManualListSort();
        $manualListSort2->setContext('global:news');
        $manualListSort2->setPId('global');
        $manualListSort2->setNamespace('content:news');
        $manualListSort2->setIdents($globalNewsIdents);

        $manager->persist($manualListSort2);

        $faqIdents = $this->getReference('testFaq2')->getIdent().','.$this->getReference('testFaq1')->getIdent();

        $manualListSort3 = new ManualListSort();
        $manualListSort3->setContext('global:faq');
        $manualListSort3->setPId('global');
        $manualListSort3->setNamespace('content:faq');
        $manualListSort3->setIdents($faqIdents);

        $manager->persist($manualListSort3);

        $manager->flush();

        $this->setReference('testManualListSortNews', $manualListSort);
        $this->setReference('testManualListSortGlobalNews', $manualListSort2);
        $this->setReference('testManualListSortFaq', $manualListSort3);
    }

    public function getDependencies()
    {
        return [
            LoadNewsData::class,
        ];
    }
}
