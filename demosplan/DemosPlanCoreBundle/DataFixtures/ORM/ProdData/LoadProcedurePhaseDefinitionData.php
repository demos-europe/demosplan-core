<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Doctrine\Persistence\ObjectManager;

class LoadProcedurePhaseDefinitionData extends ProdFixture
{
    final public const INTERNAL_CONFIGURATION_PHASE_DEFINITION = 'internalConfigurationPhaseDefinition';
    final public const EXTERNAL_CONFIGURATION_PHASE_DEFINITION = 'externalConfigurationPhaseDefinition';

    public function load(ObjectManager $manager): void
    {
        $internalConfigurationDefinition = new ProcedurePhaseDefinition();
        $internalConfigurationDefinition->setName('Konfiguration');
        $internalConfigurationDefinition->setAudience('internal');
        $internalConfigurationDefinition->setPermissionSet('hidden');
        $internalConfigurationDefinition->setOrderInAudience(0);
        $manager->persist($internalConfigurationDefinition);
        $this->setReference(self::INTERNAL_CONFIGURATION_PHASE_DEFINITION, $internalConfigurationDefinition);

        $externalConfigurationDefinition = new ProcedurePhaseDefinition();
        $externalConfigurationDefinition->setName('Konfiguration');
        $externalConfigurationDefinition->setAudience('external');
        $externalConfigurationDefinition->setPermissionSet('hidden');
        $externalConfigurationDefinition->setOrderInAudience(0);
        $manager->persist($externalConfigurationDefinition);
        $this->setReference(self::EXTERNAL_CONFIGURATION_PHASE_DEFINITION, $externalConfigurationDefinition);

        $manager->flush();
    }
}
