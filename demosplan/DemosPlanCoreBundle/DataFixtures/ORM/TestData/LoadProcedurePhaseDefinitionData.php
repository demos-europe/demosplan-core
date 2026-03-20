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
    final public const TEST_EXTERNAL_EARLY_PARTICIPATION_PHASE_DEFINITION = 'testExternalEarlyParticipationPhaseDefinition';
    final public const TEST_INTERNAL_EVALUATING_PHASE_DEFINITION = 'testInternalEvaluatingPhaseDefinition';
    final public const TEST_EXTERNAL_EVALUATING_PHASE_DEFINITION = 'testExternalEvaluatingPhaseDefinition';

    public function load(ObjectManager $manager): void
    {
        $internalConfigurationDefinition = new ProcedurePhaseDefinition();
        $internalConfigurationDefinition->setName('Konfiguration');
        $internalConfigurationDefinition->setAudience('internal');
        $internalConfigurationDefinition->setPermissionSet('hidden');
        $internalConfigurationDefinition->setOrderInAudience(0);
        $manager->persist($internalConfigurationDefinition);
        $this->setReference(self::TEST_INTERNAL_CONFIGURATION_PHASE_DEFINITION, $internalConfigurationDefinition);

        $internalParticipationDefinition = new ProcedurePhaseDefinition();
        $internalParticipationDefinition->setName('Beteiligung TöB');
        $internalParticipationDefinition->setAudience('internal');
        $internalParticipationDefinition->setPermissionSet('write');
        $internalParticipationDefinition->setOrderInAudience(1);
        $manager->persist($internalParticipationDefinition);
        $this->setReference(self::TEST_INTERNAL_PARTICIPATION_PHASE_DEFINITION, $internalParticipationDefinition);

        $internalEvaluatingDefinition = new ProcedurePhaseDefinition();
        $internalEvaluatingDefinition->setName('Auswertung');
        $internalEvaluatingDefinition->setAudience('internal');
        $internalEvaluatingDefinition->setPermissionSet('read');
        $internalEvaluatingDefinition->setParticipationState('finished');
        $internalEvaluatingDefinition->setOrderInAudience(2);
        $manager->persist($internalEvaluatingDefinition);
        $this->setReference(self::TEST_INTERNAL_EVALUATING_PHASE_DEFINITION, $internalEvaluatingDefinition);

        $internalClosedDefinition = new ProcedurePhaseDefinition();
        $internalClosedDefinition->setName('Abgeschlossen');
        $internalClosedDefinition->setAudience('internal');
        $internalClosedDefinition->setPermissionSet('read');
        $internalClosedDefinition->setOrderInAudience(3);
        $manager->persist($internalClosedDefinition);
        $this->setReference(self::TEST_INTERNAL_CLOSED_PHASE_DEFINITION, $internalClosedDefinition);

        $externalConfigurationDefinition = new ProcedurePhaseDefinition();
        $externalConfigurationDefinition->setName('Konfiguration');
        $externalConfigurationDefinition->setAudience('external');
        $externalConfigurationDefinition->setPermissionSet('hidden');
        $externalConfigurationDefinition->setOrderInAudience(0);
        $manager->persist($externalConfigurationDefinition);
        $this->setReference(self::TEST_EXTERNAL_CONFIGURATION_PHASE_DEFINITION, $externalConfigurationDefinition);

        $externalEarlyParticipationDefinition = new ProcedurePhaseDefinition();
        $externalEarlyParticipationDefinition->setName('Frühzeitige Öffentlichkeitsbeteiligung');
        $externalEarlyParticipationDefinition->setAudience('external');
        $externalEarlyParticipationDefinition->setPermissionSet('write');
        $externalEarlyParticipationDefinition->setOrderInAudience(1);
        $manager->persist($externalEarlyParticipationDefinition);
        $this->setReference(self::TEST_EXTERNAL_EARLY_PARTICIPATION_PHASE_DEFINITION, $externalEarlyParticipationDefinition);

        $externalParticipationDefinition = new ProcedurePhaseDefinition();
        $externalParticipationDefinition->setName('Öffentliche Auslegung');
        $externalParticipationDefinition->setAudience('external');
        $externalParticipationDefinition->setPermissionSet('write');
        $externalParticipationDefinition->setOrderInAudience(2);
        $manager->persist($externalParticipationDefinition);
        $this->setReference(self::TEST_EXTERNAL_PARTICIPATION_PHASE_DEFINITION, $externalParticipationDefinition);

        $externalEvaluatingDefinition = new ProcedurePhaseDefinition();
        $externalEvaluatingDefinition->setName('Auswertung');
        $externalEvaluatingDefinition->setAudience('external');
        $externalEvaluatingDefinition->setPermissionSet('read');
        $externalEvaluatingDefinition->setParticipationState('finished');
        $externalEvaluatingDefinition->setOrderInAudience(3);
        $manager->persist($externalEvaluatingDefinition);
        $this->setReference(self::TEST_EXTERNAL_EVALUATING_PHASE_DEFINITION, $externalEvaluatingDefinition);

        $externalClosedDefinition = new ProcedurePhaseDefinition();
        $externalClosedDefinition->setName('Abgeschlossen');
        $externalClosedDefinition->setAudience('external');
        $externalClosedDefinition->setPermissionSet('read');
        $externalClosedDefinition->setOrderInAudience(4);
        $manager->persist($externalClosedDefinition);
        $this->setReference(self::TEST_EXTERNAL_CLOSED_PHASE_DEFINITION, $externalClosedDefinition);

        $manager->flush();
    }
}
