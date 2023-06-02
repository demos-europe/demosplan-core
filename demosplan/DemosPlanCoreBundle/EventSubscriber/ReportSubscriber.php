<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Procedure\ProcedureEditedEvent;
use demosplan\DemosPlanCoreBundle\Event\StatementAnonymizeRpcEvent;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedureReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use Exception;

class ReportSubscriber extends BaseEventSubscriber
{
    /**
     * @var ReportService
     */
    protected $reportService;

    /**
     * @var ProcedureReportEntryFactory
     */
    private $procedureReportEntryFactory;

    public function __construct(
        ProcedureReportEntryFactory $procedureReportEntryFactory,
        ReportService $reportService
    ) {
        $this->reportService = $reportService;
        $this->procedureReportEntryFactory = $procedureReportEntryFactory;
    }

    /**
     * Subscribe to Events.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProcedureEditedEvent::class       => ['onEditProcedure'],
            StatementAnonymizeRpcEvent::class => ['onStatementAnonymizeRpc'],
        ];
    }

    /**
     * Create Report Entry if User modified procedure Metadata.
     */
    public function onEditProcedure(ProcedureEditedEvent $event): void
    {
        try {
            $inData = $event->getInData();
            $currentProcedure = $event->getOriginalProcedureArray();
            $procedureId = $event->getProcedureId();
            /** @var User $user */
            $user = $event->getUser();

            if (!isset($inData['r_externalDesc'])) {
                return;
            }
            // speichere ggf. eine Änderung des Planungsanlasses im Report
            $newExternalDesc = str_replace(["\r\n", "\r", "\n"], '<br />', $inData['r_externalDesc']);
            if ($currentProcedure['externalDesc'] !== $newExternalDesc) {
                $report = $this->procedureReportEntryFactory->createDescriptionUpdateEntry(
                    $procedureId,
                    $inData['r_externalDesc'],
                    $user
                );
                $this->reportService->persistAndFlushReportEntries($report);
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Add Report failed ', [$e]);
        }
    }

    public function onStatementAnonymizeRpc(StatementAnonymizeRpcEvent $event): void
    {
        $this->reportService->addReportsOnStatementAnonymization($event);
    }
}
