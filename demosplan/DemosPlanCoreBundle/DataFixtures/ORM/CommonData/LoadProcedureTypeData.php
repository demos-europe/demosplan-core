<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\CommonData;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\DemosFixture;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\FixtureData\FixtureGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use Doctrine\Persistence\ObjectManager;

abstract class LoadProcedureTypeData extends DemosFixture
{
    public function load(ObjectManager $manager)
    {
        $statementFormDefinition = new StatementFormDefinition();
        $manager->persist($statementFormDefinition);

        $procedureBehaviorDefinition = new ProcedureBehaviorDefinition();
        $procedureBehaviorDefinition->setHasPriorityArea(false);
        $procedureBehaviorDefinition->setAllowedToEnableMap(true);
        $manager->persist($procedureBehaviorDefinition);

        $procedureUiDefinition = new ProcedureUiDefinition();
        $manager->persist($procedureUiDefinition);

        $manager->flush();

        $procedureType = new ProcedureType(
            'Allgemeine Beteiligung',
            'Verfahrensbeschreibung der allgemeinen Beteiligung',
            $statementFormDefinition,
            $procedureBehaviorDefinition,
            $procedureUiDefinition
        );
        $manager->persist($procedureType);

        $manager->flush();
    }
}
