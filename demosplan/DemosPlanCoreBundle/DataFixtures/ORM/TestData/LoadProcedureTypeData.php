<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Exception\ExclusiveProcedureOrProcedureTypeException;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedureTypeData extends TestFixture implements DependentFixtureInterface
{
    final public const _1 = 'testProcedureType1';
    final public const BPLAN = 'testProcedureType_bplan';
    final public const BRK = 'BRK';

    private $manager;

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        $this->load_1();
        $this->loadBplan();
        $this->loadBrk();

        $manager->flush();
    }

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    private function load_1(): void
    {
        $procedureType = new ProcedureType(
            'Wind',
            'Wind Verfahrensbeschreibung',
            $this->getReference(LoadStatementFormDefinitionData::PROCEDURETYPE_1),
            $this->getReference(LoadProcedureBehaviorDefinitionData::PROCEDURETYPE_1),
            $this->getReference(LoadProcedureUiDefinitionData::PROCEDURETYPE_1)
        );
        $this->manager->persist($procedureType);
        $this->setReference(self::_1, $procedureType);
    }

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    private function loadBrk(): void
    {
        $procedureType = new ProcedureType(
            self::BRK,
            'BRK Verfahrensbeschreibung',
            $this->getReference(LoadStatementFormDefinitionData::PROCEDURETYPE_BRK),
            $this->getReference(LoadProcedureBehaviorDefinitionData::PROCEDURETYPE_BRK),
            $this->getReference(LoadProcedureUiDefinitionData::PROCEDURETYPE_BRK)
        );
        $this->manager->persist($procedureType);
        $this->setReference(self::BRK, $procedureType);
    }

    /**
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    private function loadBplan(): void
    {
        $procedureType = new ProcedureType(
            'Bauleitplanung',
            'bplan Verfahrensbeschreibung',
            $this->getReference(LoadStatementFormDefinitionData::PROCEDURETYPE_BPLAN),
            $this->getReference(LoadProcedureBehaviorDefinitionData::PROCEDURETYPE_BPLAN),
            $this->getReference(LoadProcedureUiDefinitionData::PROCEDURETYPE_BPLAN)
        );
        $this->manager->persist($procedureType);
        $this->setReference(self::BPLAN, $procedureType);
    }

    public function getDependencies()
    {
        return [
            LoadProcedureBehaviorDefinitionData::class,
            LoadProcedureUiDefinitionData::class,
            LoadStatementFormDefinitionData::class,
        ];
    }
}
