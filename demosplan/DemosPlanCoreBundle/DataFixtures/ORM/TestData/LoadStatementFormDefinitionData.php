<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use Doctrine\Persistence\ObjectManager;

class LoadStatementFormDefinitionData extends TestFixture
{
    // for procedureTypes
    public const PROCEDURETYPE_1 = 'statementFormDefinition1';
    public const PROCEDURETYPE_BPLAN = 'statementFormDefinition_bplan';
    public const PROCEDURETYPE_BRK = 'statementFormDefinitionBrk';

    // for procedures
    public const PROCEDURE_TESTPROCEDURE = 'statementFormDefinition_testProcedure';

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
        $statementFormDefinition = new StatementFormDefinition();
        $this->manager->persist($statementFormDefinition);
        $this->setReference(self::PROCEDURETYPE_1, $statementFormDefinition);
    }

    private function loadBplan(): void
    {
        $statementFormDefinition = new StatementFormDefinition();
        $this->manager->persist($statementFormDefinition);
        $this->setReference(self::PROCEDURETYPE_BPLAN, $statementFormDefinition);
    }

    private function loadBrk(): void
    {
        $statementFormDefinition = new StatementFormDefinition();
        foreach ($statementFormDefinition->getFieldDefinitions() as $fieldDefinition) {
            $fieldDefinition->setEnabled(false);
        }
        $this->manager->persist($statementFormDefinition);
        $this->setReference(self::PROCEDURETYPE_BRK, $statementFormDefinition);
    }

    private function loadTestProcedure(): void
    {
        $statementFormDefinition = new StatementFormDefinition();
        $this->manager->persist($statementFormDefinition);
        $this->setReference(self::PROCEDURE_TESTPROCEDURE, $statementFormDefinition);
    }
}
