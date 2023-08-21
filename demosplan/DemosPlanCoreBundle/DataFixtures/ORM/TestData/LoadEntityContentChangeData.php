<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadEntityContentChangeData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Statement $testStatement */
        $testStatement = $this->getReference('testStatement');

        $testEntityContentChange1 = new EntityContentChange();
        $testEntityContentChange1->setEntityType(Statement::class);
        $testEntityContentChange1->setEntityId($testStatement->getId());
        $testEntityContentChange1->setEntityField('memo');

        $manager->persist($testEntityContentChange1);
        $this->setReference('testEntityContentChange1', $testEntityContentChange1);

        $testEntityContentChange2 = new EntityContentChange();
        $testEntityContentChange2->setEntityType(Statement::class);
        $testEntityContentChange2->setEntityId($testStatement->getId());
        $testEntityContentChange2->setEntityField('text');

        $manager->persist($testEntityContentChange2);
        $this->setReference('testEntityContentChange2', $testEntityContentChange2);

        $testEntityContentChange3 = new EntityContentChange();
        $testEntityContentChange3->setEntityType(Statement::class);
        $testEntityContentChange3->setEntityId($testStatement->getId());
        $testEntityContentChange3->setEntityField(Segment::RECOMMENDATION_FIELD_NAME);

        $manager->persist($testEntityContentChange3);
        $this->setReference('testEntityContentChange3', $testEntityContentChange3);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadStatementData::class,
        ];
    }
}
