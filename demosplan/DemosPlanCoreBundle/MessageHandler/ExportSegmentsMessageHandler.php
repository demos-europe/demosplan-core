<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFailureReason;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobStatusWriter;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportResponseFileStore;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentsExportResponseBuilder;
use demosplan\DemosPlanCoreBundle\Message\ExportSegmentsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Runs a grouped segments export (DOCX/ODT or ZIP) in the background (no gateway timeout), stores the
 * result as a file and records the outcome on the {@link AssessmentTableExportJob} so the browser can
 * poll and download it.
 */
#[AsMessageHandler]
class ExportSegmentsMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportJobContextRestorer $contextRestorer,
        private readonly ExportJobFailureReason $failureReason,
        private readonly ExportJobStatusWriter $statusWriter,
        private readonly ExportResponseFileStore $fileStore,
        private readonly LoggerInterface $logger,
        private readonly ProcedureHandler $procedureHandler,
        private readonly SegmentsExportResponseBuilder $responseBuilder,
    ) {
    }

    public function __invoke(ExportSegmentsMessage $message): void
    {
        $job = $this->entityManager->find(AssessmentTableExportJob::class, $message->getJobId());
        if (!$job instanceof AssessmentTableExportJob) {
            $this->logger->error('Segments export job not found', ['jobId' => $message->getJobId()]);

            return;
        }

        $job->setStatus(AssessmentTableExportJob::STATUS_PROCESSING);
        $job->setModifiedDate(new DateTime());
        $this->entityManager->flush();

        try {
            $this->contextRestorer->restore(
                $message->getUserId(),
                $message->getCustomerId(),
                $message->getProcedureId()
            );

            $response = $this->responseBuilder->build(
                $this->procedureHandler->getProcedureWithCertainty($message->getProcedureId()),
                new ParameterBag($message->getQueryParams()),
                $message->getExportType()
            );

            $storedFile = $this->fileStore->store(
                $response,
                $message->getUserId(),
                $message->getProcedureId(),
                'Synopse.'.$message->getExportType()
            );
            $job->setFileHash($storedFile->getFileHash());
            $job->setFileName($storedFile->getFileName());
            $job->setStatus(AssessmentTableExportJob::STATUS_COMPLETED);
        } catch (Throwable $e) {
            $this->logger->error('Asynchronous segments export failed', ['jobId' => $message->getJobId(), 'exception' => $e]);
            $job->setStatus(AssessmentTableExportJob::STATUS_FAILED);
            $job->setErrorMessage($this->failureReason->forThrowable($e));
        } finally {
            $job->setModifiedDate(new DateTime());
            $this->statusWriter->persist($job);
        }
    }
}
