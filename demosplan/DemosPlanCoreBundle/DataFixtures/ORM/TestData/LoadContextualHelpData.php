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
use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadContextualHelpData extends TestFixture
{
    public function load(ObjectManager $manager): void
    {
        $contextualHelp1 = new ContextualHelp();
        $contextualHelp1->setKey('help.key1');
        $contextualHelp1->setText('Ich bin die Kontexthilfe für den Weiterentwicklungsbereich.');
        $contextualHelp1->setCreateDate(new DateTime());
        $contextualHelp1->setModifyDate(new DateTime());

        $manager->persist($contextualHelp1);

        $contextualHelp2 = new ContextualHelp();
        $contextualHelp2->setKey('help.key2');
        $contextualHelp2->setText('Ich bin die Kontexthilfe für den Weiterentwicklungsbereich.');
        $contextualHelp2->setCreateDate(new DateTime());
        $contextualHelp2->setModifyDate(new DateTime());

        $manager->persist($contextualHelp2);
        $manager->flush();
        $this->setReference('testContextualHelp', $contextualHelp1);
    }
}
