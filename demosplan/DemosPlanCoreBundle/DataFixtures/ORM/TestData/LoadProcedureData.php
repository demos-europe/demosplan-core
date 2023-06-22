<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Factory\Procedure\ProcedureFactory;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class LoadProcedureData extends TestFixture implements DependentFixtureInterface
{
    final public const TESTPROCEDURE = 'testProcedure';
    final public const TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE = 'procedureInPublicParticipationPhase';
    final public const TEST_PROCEDURE_2 = 'testProcedure2';
    private $existingExternalPhasesWrite;
    private $existingInternalPhasesWrite;

    public function __construct(EntityManagerInterface $entityManager, GlobalConfigInterface $globalConfig)
    {
        parent::__construct($entityManager);

        $this->existingInternalPhasesWrite = $globalConfig->getInternalPhaseKeys('write');
        $this->existingExternalPhasesWrite = $globalConfig->getExternalPhaseKeys('write');
    }

    public function load(ObjectManager $manager): void
    {

        $procedure = ProcedureFactory::createMany(10);
        $this->setReference(self::TESTPROCEDURE, $procedure[0]->object());
        $this->setReference(self::TEST_PROCEDURE_2, $procedure[1]->object());
        $this->setReference('procedureToDelete', $procedure[2]->object());
        $this->setReference('testProcedure3', $procedure[3]->object());
        $this->setReference('masterBlaupause', $procedure[4]->object());
        $this->setReference('masterBlaupause2', $procedure[5]->object());
        $this->setReference('testmasterProcedureWithBoilerplates', $procedure[6]->object());
        $this->setReference('procedureInPublicParticipationPhase', $procedure[7]->object());
//        $manager->persist($procedure);
//
//        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureBehaviorDefinitionData::class,
            LoadProcedureTypeData::class,
            LoadProcedureUiDefinitionData::class,
            LoadStatementFormDefinitionData::class,
            LoadUserData::class,
        ];
    }
}
