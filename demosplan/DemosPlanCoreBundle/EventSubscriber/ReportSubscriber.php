<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\CreateReportEntryEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\ProcedureEditedEvent;
use demosplan\DemosPlanCoreBundle\Event\StatementAnonymizeRpcEvent;
use demosplan\DemosPlanCoreBundle\Logic\Report\ElementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ParagraphReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\PlanDrawReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedureReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\SingleDocumentReportEntryFactory;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\JsonApi\Event\AfterCreationEvent;
use EDT\JsonApi\Event\AfterDeletionEvent;
use EDT\JsonApi\Event\AfterUpdateEvent;
use Exception;

class ReportSubscriber extends BaseEventSubscriber
{
    /**
     * @var ReportService
     */
    protected $reportService;

    public function __construct(
        private readonly ProcedureReportEntryFactory $procedureReportEntryFactory,
        private readonly ElementReportEntryFactory $elementReportEntryFactory,
        private readonly SingleDocumentReportEntryFactory $singleDocumentReportEntryFactory,
        private readonly ParagraphReportEntryFactory $paragraphReportEntryFactory,
        private readonly PlanDrawReportEntryFactory $planDrawReportEntryFactory,
        ReportService $reportService
    ) {
        $this->reportService = $reportService;
    }

    /**
     * Subscribe to Events.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CreateReportEntryEvent::class       => ['createReportEntry'],
            AfterUpdateEvent::class             => ['afterCreateUpdateOrDelete'],
            AfterDeletionEvent::class           => ['afterCreateUpdateOrDelete'],
            AfterCreationEvent::class           => ['afterCreateUpdateOrDelete'],
            ProcedureEditedEvent::class         => ['onEditProcedure'],
            StatementAnonymizeRpcEvent::class   => ['onStatementAnonymizeRpc'],
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

            if (isset($inData['r_externalDesc'])) {
                // speichere ggf. eine Änderung des Planungsanlasses im Report
                $newExternalDesc = str_replace(["\r\n", "\r", "\n"], '<br />', (string) $inData['r_externalDesc']);
                if ($currentProcedure['externalDesc'] !== $newExternalDesc) {
                    $report = $this->procedureReportEntryFactory->createDescriptionUpdateEntry(
                        $procedureId,
                        $inData['r_externalDesc'],
                        $user
                    );
                    $this->reportService->persistAndFlushReportEntries($report);
                }
            }

            $this->createPlanDrawReportOnDemand($event);

        } catch (Exception $e) {
            $this->getLogger()->error('Add Report failed ', [$e]);
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws JsonException
     */
    private function createPlanDrawReportOnDemand(ProcedureEditedEvent $event): void
    {
        $originPlanPDF = $event->getOriginalProcedureArray()['planPDF'] ?? null;
        $originPlanDrawPDF = $event->getOriginalProcedureArray()['planDrawPDF'] ?? null;

        $incomingPlanPDF = $event->getInData()['planPDF'] ?? null;
        $incomingPlanDrawPDF = $event->getInData()['planDrawPDF'] ?? null;


        if ((null !== $originPlanPDF && null !== $incomingPlanPDF)
            ||(null !== $originPlanDrawPDF && null !== $incomingPlanDrawPDF))
        {
            $report = $this->planDrawReportEntryFactory->createPlanDrawEntry(
                $event->getProcedureId(),
                $originPlanPDF,
                $incomingPlanPDF,
                $originPlanDrawPDF,
                $incomingPlanDrawPDF
            );

            $this->reportService->persistAndFlushReportEntries($report);
        }
    }

    public function onStatementAnonymizeRpc(StatementAnonymizeRpcEvent $event): void
    {
        $this->reportService->addReportsOnStatementAnonymization($event);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createReportEntry(CreateReportEntryEvent $event): void
    {
        $this->dynamicCreateReportEntry($event->getEntity(), $event->getCategory());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function afterCreateUpdateOrDelete(AfterDeletionEvent|AfterCreationEvent|AfterUpdateEvent $event): void
    {
        $reportCategory = 'unknown';
        switch (true) {
            case $event instanceof AfterCreationEvent:
                $reportCategory = ReportEntry::CATEGORY_ADD;
                break;
            case $event instanceof AfterUpdateEvent:
                $reportCategory = ReportEntry::CATEGORY_UPDATE;
                break;
            case $event instanceof AfterDeletionEvent:
                $reportCategory = ReportEntry::CATEGORY_DELETE;
                break;
        }

        //fixme: in case of AfterDeletionEvent there is no entity, only the entityIdentifier!
        //fixme: not implemented yet: AfterDeletionEvent does not have an entity, only an getEntityIdentifier
        // this needs to be handled in case of deletion of one of these three entities, via ResourceTypes.
//        if ($event->getEntity() instanceof Elements
//            || $event->getEntity() instanceof SingleDocument
//            || $event->getEntity() instanceof Paragraph) {
//            $this->dynamicCreateReportEntry($event->getEntity(), $reportCategory);
//        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function dynamicCreateReportEntry(Elements|Paragraph|SingleDocument $entity, string $category): void
    {
        switch ($category) {
            case ReportEntry::CATEGORY_ADD:
                $date = $entity->getCreateDate()->getTimestamp();
                break;
            case ReportEntry::CATEGORY_UPDATE:
                $date = $entity->getModifyDate()->getTimestamp();
                break;
            default:
                $date = Carbon::now()->getTimestamp();
                break;
        }


        switch (true) {
            case $entity instanceof Elements:
                /** @var Elements $element */
                $element = $entity;
                $report = $this->elementReportEntryFactory->createElementEntry(
                    $element,
                    $category,
                    $date
                );
                break;
            case $entity instanceof SingleDocument:
                /** @var SingleDocument $singleDocument */
                $singleDocument = $entity;
                $report = $this->singleDocumentReportEntryFactory->createSingleDocumentEntry(
                    $singleDocument,
                    $category,
                    $date
                );
                break;
            case $entity instanceof Paragraph:
                /** @var Paragraph $paragraph */
                $paragraph = $entity;
                $report = $this->paragraphReportEntryFactory->createParagraphEntry(
                    $paragraph,
                    $category,
                    $date
                );
                break;
        }

        $this->reportService->persistAndFlushReportEntries($report);
    }
}
