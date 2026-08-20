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
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableExporterStrategy;
use demosplan\DemosPlanCoreBundle\Message\ExportAssessmentTableMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Runs an Abwägungstabelle export in the background (no gateway timeout), stores the result as a
 * file and records the outcome on the {@link AssessmentTableExportJob} so the browser can poll and
 * download it.
 *
 * The existing synchronous exporter is reused unchanged; this handler only re-establishes the
 * request-scoped context (session filter hash, current user, current procedure, permissions) that
 * the exporter would normally get from the HTTP request.
 */
#[AsMessageHandler]
class ExportAssessmentTableMessageHandler
{
    public function __construct(
        private readonly AssessmentTableExporterStrategy $assessmentExporter,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportJobContextRestorer $contextRestorer,
        private readonly ExportJobFailureReason $failureReason,
        private readonly ExportJobStatusWriter $statusWriter,
        private readonly ExportResponseFileStore $fileStore,
        private readonly FileResponseGeneratorStrategy $responseGenerator,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(ExportAssessmentTableMessage $message): void
    {
        $job = $this->entityManager->find(AssessmentTableExportJob::class, $message->getJobId());
        if (!$job instanceof AssessmentTableExportJob) {
            $this->logger->error('Assessment table export job not found', ['jobId' => $message->getJobId()]);

            return;
        }

        $job->setStatus(AssessmentTableExportJob::STATUS_PROCESSING);
        $job->setModifiedDate(new DateTime());
        $this->entityManager->flush();

        $requestPushed = false;
        try {
            // Rebuild the request/session the exporter relies on (only the filter hash list is
            // genuinely request-scoped; everything else is reloaded from the database below).
            $this->pushSyntheticRequest($message->getHashList());
            $requestPushed = true;

            $this->contextRestorer->restore(
                $message->getUserId(),
                $message->getCustomerId(),
                $message->getProcedureId()
            );

            $file = $this->assessmentExporter->export($message->getExportFormat(), $message->getParameters());
            $response = ($this->responseGenerator)($message->getExportFormat(), $file);

            $storedFile = $this->fileStore->store(
                $response,
                $message->getUserId(),
                $message->getProcedureId(),
                'Abwaegungstabelle'
            );
            $job->setFileHash($storedFile->getFileHash());
            $job->setFileName($storedFile->getFileName());
            $job->setStatus(AssessmentTableExportJob::STATUS_COMPLETED);
        } catch (Throwable $e) {
            $this->logger->error('Asynchronous assessment table export failed', ['jobId' => $message->getJobId(), 'exception' => $e]);
            $job->setStatus(AssessmentTableExportJob::STATUS_FAILED);
            $job->setErrorMessage($this->failureReason->forThrowable($e));
        } finally {
            if ($requestPushed) {
                $this->requestStack->pop();
            }
            $job->setModifiedDate(new DateTime());
            $this->statusWriter->persist($job);
        }
    }

    private function pushSyntheticRequest(array $hashList): void
    {
        $session = new Session(new MockArraySessionStorage());
        if ([] !== $hashList) {
            $session->set('hashList', $hashList);
        }
        $request = new Request();
        $request->setSession($session);
        $this->requestStack->push($request);
    }
}
