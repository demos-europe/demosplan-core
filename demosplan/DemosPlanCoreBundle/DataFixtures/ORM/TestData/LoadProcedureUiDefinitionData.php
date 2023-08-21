<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedureUiDefinitionData extends TestFixture
{
    // for procedureTypes
    final public const PROCEDURETYPE_1 = 'procedureUiDefinition1';
    final public const PROCEDURETYPE_BPLAN = 'procedureUiDefinition_bplan';
    final public const PROCEDURETYPE_BRK = 'procedureUiDefinitionBrk';

    // for procedures
    final public const PROCEDURE_TESTPROCEDURE = 'procedureUiDefinition_procedureTest';

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
        $procedureUiDefinition = new ProcedureUiDefinition();
        $procedureUiDefinition->setMapHintDefault('Wer nicht von dreitausend Jahren');
        $procedureUiDefinition->setStatementFormHintStatement('Sich weiß Rechenschaft zu geben');
        $procedureUiDefinition->setStatementFormHintPersonalData('Bleibt im Dunklen unerfahren');
        $procedureUiDefinition->setStatementFormHintRecheck('mag von Tag zu Tage leben.');
        $procedureUiDefinition->setStatementPublicSubmitConfirmationText('Bitte notieren Sie sich die Vorgangsnummer ${SUBMIT_ID}. Diese wird in der Regel benötigt, um zu einem späteren Zeitpunkt die Bewertung ihrer Stellungnahme abzurufen, da die Ergebnisveröffentlichung anonymisiert erfolgt.');
        $this->manager->persist($procedureUiDefinition);
        $this->setReference(self::PROCEDURETYPE_1, $procedureUiDefinition);
    }

    private function loadBplan(): void
    {
        $procedureUiDefinition = new ProcedureUiDefinition();
        $this->manager->persist($procedureUiDefinition);
        $this->setReference(self::PROCEDURETYPE_BPLAN, $procedureUiDefinition);
    }

    private function loadBrk(): void
    {
        $procedureUiDefinition = new ProcedureUiDefinition();
        $this->manager->persist($procedureUiDefinition);
        $this->setReference(self::PROCEDURETYPE_BRK, $procedureUiDefinition);
    }

    private function loadTestProcedure(): void
    {
        $procedureUiDefinition = new ProcedureUiDefinition();
        $procedureUiDefinition->setMapHintDefault(
            'This is a mapHintDefault of a procedureUiDefinition on a procedure.'
        );
        $this->manager->persist($procedureUiDefinition);
        $this->setReference(self::PROCEDURE_TESTPROCEDURE, $procedureUiDefinition);
    }
}
