<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedureBehaviorDefinitionData extends TestFixture implements DependentFixtureInterface
{
    // for procedureTypes
    final public const PROCEDURETYPE_1 = 'procedureBehaviorDefinition1';
    final public const PROCEDURETYPE_BPLAN = 'procedureBehaviorDefinition_bplan';
    final public const PROCEDURETYPE_BRK = 'BRK';

    // for procedures
    final public const PROCEDURE_TESTPROCEDURE = 'procedureBehaviorDefinition_procedureTest';

    private $manager;

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        $this->load1();
        $this->loadBplan();
        $this->loadBrk();
        $this->loadTestProcedure();

        $manager->flush();
    }

    private function load1(): void
    {
        $procedureBehaviorDefinition = new ProcedureBehaviorDefinition();
        $procedureBehaviorDefinition->setHasPriorityArea(false);
        $procedureBehaviorDefinition->setAllowedToEnableMap(true);
        $this->manager->persist($procedureBehaviorDefinition);
        $this->setReference(self::PROCEDURETYPE_1, $procedureBehaviorDefinition);
    }

    private function loadBplan(): void
    {
        $procedureBehaviorDefinition = new ProcedureBehaviorDefinition();
        $procedureBehaviorDefinition->setHasPriorityArea(false);
        $procedureBehaviorDefinition->setAllowedToEnableMap(true);
        $this->manager->persist($procedureBehaviorDefinition);
        $this->setReference(self::PROCEDURETYPE_BPLAN, $procedureBehaviorDefinition);
    }

    private function loadBrk(): void
    {
        $procedureBehaviorDefinition = new ProcedureBehaviorDefinition();
        $procedureBehaviorDefinition->setHasPriorityArea(false);
        $procedureBehaviorDefinition->setAllowedToEnableMap(true);
        $procedureBehaviorDefinition->setParticipationGuestOnly(true);
        $this->manager->persist($procedureBehaviorDefinition);
        $this->setReference(self::PROCEDURETYPE_BRK, $procedureBehaviorDefinition);
    }

    private function loadTestProcedure(): void
    {
        $procedureBehaviorDefinition = new ProcedureBehaviorDefinition();
        $procedureBehaviorDefinition->setHasPriorityArea(true);
        $procedureBehaviorDefinition->setAllowedToEnableMap(true);
        $this->manager->persist($procedureBehaviorDefinition);
        $this->setReference(self::PROCEDURE_TESTPROCEDURE, $procedureBehaviorDefinition);
    }

    public function getDependencies(): array
    {
        return [
            LoadStatementFormDefinitionData::class,
        ];
    }
}
