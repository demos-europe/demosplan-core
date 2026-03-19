<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedurePhaseDefinitionData extends TestFixture
{
    final public const TEST_INTERNAL_PARTICIPATION_PHASE_DEFINITION = 'testInternalParticipationPhaseDefinition';
    final public const TEST_EXTERNAL_PARTICIPATION_PHASE_DEFINITION = 'testExternalParticipationPhaseDefinition';
    final public const TEST_INTERNAL_CONFIGURATION_PHASE_DEFINITION = 'testInternalConfigurationPhaseDefinition';
    final public const TEST_EXTERNAL_CONFIGURATION_PHASE_DEFINITION = 'testExternalConfigurationPhaseDefinition';
    final public const TEST_INTERNAL_CLOSED_PHASE_DEFINITION = 'testInternalClosedPhaseDefinition';
    final public const TEST_EXTERNAL_CLOSED_PHASE_DEFINITION = 'testExternalClosedPhaseDefinition';

    public function load(ObjectManager $manager): void
    {
        $internalDefinition = new ProcedurePhaseDefinition();
        $internalDefinition->setName('Beteiligung TöB');
        $internalDefinition->setAudience('internal');
        $internalDefinition->setPermissionSet('write');
        $internalDefinition->setOrderInAudience(1);
        $manager->persist($internalDefinition);
        $this->setReference(self::TEST_INTERNAL_PARTICIPATION_PHASE_DEFINITION, $internalDefinition);

        $externalDefinition = new ProcedurePhaseDefinition();
        $externalDefinition->setName('Öffentliche Auslegung');
        $externalDefinition->setAudience('external');
        $externalDefinition->setPermissionSet('write');
        $externalDefinition->setOrderInAudience(1);
        $manager->persist($externalDefinition);
        $this->setReference(self::TEST_EXTERNAL_PARTICIPATION_PHASE_DEFINITION, $externalDefinition);

        $internalConfigurationDefinition = new ProcedurePhaseDefinition();
        $internalConfigurationDefinition->setName('Konfiguration');
        $internalConfigurationDefinition->setAudience('internal');
        $internalConfigurationDefinition->setPermissionSet('hidden');
        $internalConfigurationDefinition->setOrderInAudience(0);
        $manager->persist($internalConfigurationDefinition);
        $this->setReference(self::TEST_INTERNAL_CONFIGURATION_PHASE_DEFINITION, $internalConfigurationDefinition);

        $externalConfigurationDefinition = new ProcedurePhaseDefinition();
        $externalConfigurationDefinition->setName('Konfiguration');
        $externalConfigurationDefinition->setAudience('external');
        $externalConfigurationDefinition->setPermissionSet('hidden');
        $externalConfigurationDefinition->setOrderInAudience(0);
        $manager->persist($externalConfigurationDefinition);
        $this->setReference(self::TEST_EXTERNAL_CONFIGURATION_PHASE_DEFINITION, $externalConfigurationDefinition);

        $closedDefinition = new ProcedurePhaseDefinition();
        $closedDefinition->setName('Abgeschlossen');
        $closedDefinition->setAudience('internal');
        $closedDefinition->setPermissionSet('read');
        $closedDefinition->setOrderInAudience(2);
        $manager->persist($closedDefinition);
        $this->setReference(self::TEST_INTERNAL_CLOSED_PHASE_DEFINITION, $closedDefinition);

        $externalClosedDefinition = new ProcedurePhaseDefinition();
        $externalClosedDefinition->setName('Abgeschlossen');
        $externalClosedDefinition->setAudience('external');
        $externalClosedDefinition->setPermissionSet('read');
        $externalClosedDefinition->setOrderInAudience(2);
        $manager->persist($externalClosedDefinition);
        $this->setReference(self::TEST_EXTERNAL_CLOSED_PHASE_DEFINITION, $externalClosedDefinition);

        $manager->flush();
    }
}
