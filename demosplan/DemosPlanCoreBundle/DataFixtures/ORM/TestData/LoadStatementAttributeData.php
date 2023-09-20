<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadStatementAttributeData extends TestFixture
{
    public function load(ObjectManager $manager): void
    {
        $statementAttribute1 = new StatementAttribute();
        $statementAttribute1->setType('priorityAreaType');
        $statementAttribute1->setValue('positive');

        $this->setReference('testStatementAttribute1', $statementAttribute1);
        $manager->persist($statementAttribute1);

        $statementAttribute2 = new StatementAttribute();
        $statementAttribute2->setType('county');
        $statementAttribute2->setValue('test_Kreis_XYZ');

        $this->setReference('testStatementAttribute2', $statementAttribute2);
        $manager->persist($statementAttribute2);

        $statementAttribute3 = new StatementAttribute();
        $statementAttribute3->setType('noLocation');
        $statementAttribute3->setValue('1');

        $this->setReference('testStatementAttribute3', $statementAttribute3);
        $manager->persist($statementAttribute3);

        $manager->flush();
    }
}
