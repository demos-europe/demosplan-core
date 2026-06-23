<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionUpdatableField;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;

class ProcedurePhaseDefinitionEditor
{
    public function __construct(
        private readonly MessageBagInterface $messageBag,
        private readonly ProcedurePhaseDefinitionReportEntryFactory $reportEntryFactory,
        private readonly ProcedurePhaseDefinitionRepository $procedurePhaseDefinitionRepository,
        private readonly ReportService $reportService,
    ) {
    }

    public function guardConfigurationPhaseNotEditable(ProcedurePhaseDefinition $phaseDefinition): void
    {
        if ($phaseDefinition->isConfigurationPhase()) {
            throw new BadRequestException('Only the name of the configuration phase can be changed; permissionSet and participationState are fixed.');
        }
    }

    public function setDeleted(ProcedurePhaseDefinition $phaseDefinition, bool $isDeleted): void
    {
        if ($isDeleted && $this->procedurePhaseDefinitionRepository->isReferencedByActiveProcedure($phaseDefinition)) {
            $this->messageBag->add(
                'error',
                'error.procedure_phase_definition.delete.referenced',
                ['phase' => $phaseDefinition->getName()]);

            return;
        }

        $phaseDefinition->setDeleted($isDeleted);
    }

    /**
     * @throws JsonException
     */
    public function addReportEntryUpdate(
        ProcedurePhaseDefinition $procedurePhaseDefinition,
        ProcedurePhaseDefinitionUpdatableField $field,
        mixed $oldValue,
        mixed $newValue,
    ): void {
        if ($oldValue === $newValue) {
            return;
        }

        $reportEntry = $this->reportEntryFactory->createProcedurePhaseDefinitionUpdateEntry(
            $procedurePhaseDefinition,
            $field,
            $oldValue,
            $newValue,
        );
        $this->reportService->persistAndFlushReportEntry($reportEntry);
    }
}
