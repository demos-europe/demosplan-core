<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Procedure\ProcedureEditedEvent;
use demosplan\DemosPlanCoreBundle\Event\StatementAnonymizeRpcEvent;
use demosplan\DemosPlanCoreBundle\Logic\Report\ElementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ParagraphReportEntryFactory;
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
            AfterUpdateEvent::class             => ['afterUpdate'],
            AfterDeletionEvent::class           => ['afterDeletion'],
            AfterCreationEvent::class           => ['afterCreation'],
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

            if (!isset($inData['r_externalDesc'])) {
                return;
            }
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
        } catch (Exception $e) {
            $this->getLogger()->error('Add Report failed ', [$e]);
        }
    }

    public function onStatementAnonymizeRpc(StatementAnonymizeRpcEvent $event): void
    {
        $this->reportService->addReportsOnStatementAnonymization($event);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException|\Doctrine\ORM\ORMException
     */
    public function afterUpdate(AfterUpdateEvent $event): void
    {
        switch ($event->getType()->getName()) {
            case 'Elements':
                /** @var Elements $element */
                $element = $event->getEntity();
                $report = $this->elementReportEntryFactory->createElementEntry(
                    $element,
                    ReportEntry::CATEGORY_UPDATE,
                    $element->getModifyDate()->getTimestamp()
                );
                break;
            case 'SingleDocument':
                /** @var SingleDocument $singleDocument */
                $singleDocument = $event->getEntity();
                $report = $this->singleDocumentReportEntryFactory->createSingleDocumentEntry(
                    $singleDocument,
                    ReportEntry::CATEGORY_UPDATE,
                    $singleDocument->getModifyDate()->getTimestamp()
                );
                break;
            case 'Paragraph':
                /** @var Paragraph $paragraph */
                $paragraph = $event->getEntity();
                $report = $this->paragraphReportEntryFactory->createParagraphEntry(
                    $paragraph,
                    ReportEntry::CATEGORY_UPDATE,
                    $paragraph->getModifyDate()->getTimestamp()
                );
                break;
        }

        $this->reportService->persistAndFlushReportEntries($report);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function afterCreation(AfterCreationEvent $event): void
    {
        switch ($event->getType()->getName()) {
            case 'Elements':
                /** @var Elements $element */
                $element = $event->getEntity();
                $report = $this->elementReportEntryFactory->createElementEntry(
                    $element,
                    ReportEntry::CATEGORY_ADD,
                    $element->getCreateDate()->getTimestamp()
                );
                break;
            case 'SingleDocument':
                /** @var SingleDocument $singleDocument */
                $singleDocument = $event->getEntity();
                $report = $this->singleDocumentReportEntryFactory->createSingleDocumentEntry(
                    $singleDocument,
                    ReportEntry::CATEGORY_ADD,
                    $singleDocument->getCreateDate()->getTimestamp()
                );
                break;
            case 'Paragraph':
                /** @var Paragraph $paragraph */
                $paragraph = $event->getEntity();
                $report = $this->paragraphReportEntryFactory->createParagraphEntry(
                    $paragraph,
                    ReportEntry::CATEGORY_ADD,
                    $paragraph->getCreateDate()->getTimestamp()
                );
                break;
        }

        $this->reportService->persistAndFlushReportEntries($report);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function afterDeletion(AfterCreationEvent $event): void
    {
        switch ($event->getType()->getName()) {
            case 'Elements':
                /** @var Elements $element */
                $element = $event->getEntity();
                $report = $this->elementReportEntryFactory->createElementEntry(
                    $element,
                    ReportEntry::CATEGORY_DELETE,
                );
                break;
            case 'SingleDocument':
                /** @var SingleDocument $singleDocument */
                $singleDocument = $event->getEntity();
                $report = $this->singleDocumentReportEntryFactory->createSingleDocumentEntry(
                    $singleDocument,
                    ReportEntry::CATEGORY_DELETE,
                );
                break;
            case 'Paragraph':
                /** @var Paragraph $paragraph */
                $paragraph = $event->getEntity();
                $report = $this->paragraphReportEntryFactory->createParagraphEntry(
                    $paragraph,
                    ReportEntry::CATEGORY_DELETE,
                );
                break;
        }

        $this->reportService->persistAndFlushReportEntries($report);
    }

}
