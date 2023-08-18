<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use Doctrine\Persistence\ObjectManager;

class LoadProcedureTypeData extends ProdFixture
{
    public function load(ObjectManager $manager): void
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
