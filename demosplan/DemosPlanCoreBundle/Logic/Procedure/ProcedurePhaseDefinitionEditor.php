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

use DemosEurope\DemosplanAddon\Contracts\Events\ProcedurePhaseDefinitionMarkedAsDeletedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Event\ProcedurePhaseDefinitionMarkedAsDeletedEvent;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionUpdatableField;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProcedurePhaseDefinitionEditor
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
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

    /**
     * @throws JsonException
     * @throws PersistResourceException
     */
    public function setDeleted(ProcedurePhaseDefinition $phaseDefinition, bool $newIsDeleted): void
    {
        if ($newIsDeleted && $this->procedurePhaseDefinitionRepository->isReferencedByActiveProcedure($phaseDefinition)) {
            $this->messageBag->add(
                'error',
                'error.procedure_phase_definition.delete.referenced',
                ['phase' => $phaseDefinition->getName()]
            );

            throw new PersistResourceException('Phase definition is still referenced by an active procedure.');
        }

        $oldIsDeleted = $phaseDefinition->isDeleted();
        $phaseDefinition->setDeleted($newIsDeleted);
        $this->addReportEntryUpdate(
            $phaseDefinition,
            ProcedurePhaseDefinitionUpdatableField::IS_DELETED,
            $oldIsDeleted,
            $newIsDeleted,
        );

        if ($newIsDeleted) {
            $this->eventDispatcher->dispatch(
                new ProcedurePhaseDefinitionMarkedAsDeletedEvent($phaseDefinition),
                ProcedurePhaseDefinitionMarkedAsDeletedEventInterface::class
            );
        }
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
