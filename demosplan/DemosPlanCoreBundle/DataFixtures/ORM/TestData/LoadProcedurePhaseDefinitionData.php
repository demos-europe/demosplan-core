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
    final public const TEST_INTERNAL_PHASE_DEFINITION = 'testInternalPhaseDefinition';
    final public const TEST_EXTERNAL_PHASE_DEFINITION = 'testExternalPhaseDefinition';

    public function load(ObjectManager $manager): void
    {
        $internalDefinition = new ProcedurePhaseDefinition();
        $internalDefinition->setName('Beteiligung TöB');
        $internalDefinition->setAudience('internal');
        $internalDefinition->setPermissionSet('write');
        $internalDefinition->setOrderInAudience(1);
        $manager->persist($internalDefinition);
        $this->setReference(self::TEST_INTERNAL_PHASE_DEFINITION, $internalDefinition);

        $externalDefinition = new ProcedurePhaseDefinition();
        $externalDefinition->setName('Öffentliche Auslegung');
        $externalDefinition->setAudience('external');
        $externalDefinition->setPermissionSet('write');
        $externalDefinition->setOrderInAudience(1);
        $manager->persist($externalDefinition);
        $this->setReference(self::TEST_EXTERNAL_PHASE_DEFINITION, $externalDefinition);

        $manager->flush();
    }
}
