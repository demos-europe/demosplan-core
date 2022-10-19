<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Event\DailyMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use Psr\Log\LoggerInterface;

class DailyMaintenanceSubscriber extends BaseEventSubscriber
{
    /**
     * @var AnnotatedStatementPdfHandler
     */
    private $annotatedStatementPdfHandler;
    /**
     * @var Permissions
     */
    private $permissions;

    public function __construct(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        LoggerInterface $logger,
        Permissions $permissions
    ) {
        $this->annotatedStatementPdfHandler = $annotatedStatementPdfHandler;
        $this->logger = $logger;
        $this->permissions = $permissions;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DailyMaintenanceEvent::class => 'handleDailyMaintenance',
        ];
    }

    public function handleDailyMaintenance(DailyMaintenanceEvent $dailyMaintenanceEvent): void
    {
        // Rollback AnnotatedStatementPdf in box- or text-review status
        if ($this->permissions->hasPermission('feature_annotated_statement_pdf_rollback_review_status')) {
            $this->logger->info('Maintenance: Bringing all AnnotatedStatementPdf which are in boxes_review status back to ready_to_review');
            $boxReviewCount = $this->annotatedStatementPdfHandler->rollbackBoxReviewStatus();
            $this->logger->info("Maintenance: $boxReviewCount AnnotatedStatementPdfs in boxes_review status brought back to ready_to_review");

            $this->logger->info('Maintenance: Bringing all AnnotatedStatementPdf which are in text_review status back to ready_to_convert');
            $textReviewCount = $this->annotatedStatementPdfHandler->rollbackTextReviewStatus();
            $this->logger->info("Maintenance: $textReviewCount AnnotatedStatementPdfs in text_review status brought back to ready_to_convert");
        }
        $this->logger->info('Daily Maintenance Tasks completed');
    }
}
