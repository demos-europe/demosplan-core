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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureExportJob;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFailureReason;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobStatusWriter;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportResponseFileStore;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ExportService;
use demosplan\DemosPlanCoreBundle\Message\ExportProcedureMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Runs a procedure export (Gesamtabzug) in the background (no gateway timeout), stores the resulting
 * ZIP as a file and records the outcome on the {@link ProcedureExportJob} so the browser can poll
 * and download it.
 *
 * The existing synchronous {@link ExportService::generateProcedureExportZip()} is reused unchanged;
 * this handler only re-establishes the acting user and permissions that it would normally get from
 * the HTTP request.
 */
#[AsMessageHandler]
class ExportProcedureMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportJobContextRestorer $contextRestorer,
        private readonly ExportJobFailureReason $failureReason,
        private readonly ExportJobStatusWriter $statusWriter,
        private readonly ExportResponseFileStore $fileStore,
        private readonly ExportService $exportService,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ExportProcedureMessage $message): void
    {
        $job = $this->entityManager->find(ProcedureExportJob::class, $message->getJobId());
        if (!$job instanceof ProcedureExportJob) {
            $this->logger->error('Procedure export job not found', ['jobId' => $message->getJobId()]);

            return;
        }

        $job->setStatus(ProcedureExportJob::STATUS_PROCESSING);
        $job->setModifiedDate(new DateTime());
        $this->entityManager->flush();

        $requestPushed = false;
        try {
            // The procedure export builds its own query parameters, but nested assessment-table
            // builders may still reach for the request/session; provide an empty one so they do not
            // fail outside an HTTP request.
            $this->pushSyntheticRequest();
            $requestPushed = true;

            // No procedure id: a procedure export covers a selection, and ExportService sets each
            // procedure (and checks access to it) itself while building the archive.
            $this->contextRestorer->restore($message->getUserId(), $message->getCustomerId());

            $response = $this->exportService->generateProcedureExportZip(
                $message->getProcedureIds(),
                $message->useExternalProcedureName()
            );

            $storedFile = $this->fileStore->store(
                $response,
                $message->getUserId(),
                null,
                // Same key ExportService names the archive after, so the fallback cannot diverge
                // from it per project or locale.
                $this->translator->trans('procedure.export_filename').'.zip'
            );
            $job->setFileHash($storedFile->getFileHash());
            $job->setFileName($storedFile->getFileName());
            $job->setStatus(ProcedureExportJob::STATUS_COMPLETED);
        } catch (Throwable $e) {
            $this->logger->error('Asynchronous procedure export failed', ['jobId' => $message->getJobId(), 'exception' => $e]);
            $job->setStatus(ProcedureExportJob::STATUS_FAILED);
            $job->setErrorMessage($this->failureReason->forThrowable($e));
        } finally {
            if ($requestPushed) {
                $this->requestStack->pop();
            }
            $job->setModifiedDate(new DateTime());
            $this->statusWriter->persist($job);
        }
    }

    private function pushSyntheticRequest(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);
    }
}
