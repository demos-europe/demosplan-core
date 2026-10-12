<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Starts, polls and downloads procedure-scoped background exports, tracked as
 * {@link AssessmentTableExportJob}. Status and download are restricted to the job's owner.
 */
class ProcedureExportJobService
{
    public function __construct(
        private readonly CurrentUserService $currentUserService,
        private readonly ExportJobDownloadResponseFactory $downloadResponseFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly RunningExportJobLookup $runningExportJobLookup,
    ) {
    }

    /**
     * The export gives no progress feedback, so a slow one invites re-triggering. Hand back the job
     * that is already running for this exact request instead of queueing a duplicate on the serial
     * worker; the client then polls the running job rather than orphaning it.
     *
     * @param callable(string): object $createMessage builds the worker message for the new job's id
     *
     * @return string id of the job to poll
     */
    public function start(string $userId, string $procedureId, string $parametersHash, callable $createMessage): string
    {
        $running = $this->runningExportJobLookup->find(
            AssessmentTableExportJob::class,
            [
                'userId'         => $userId,
                'procedureId'    => $procedureId,
                'parametersHash' => $parametersHash,
            ],
            [AssessmentTableExportJob::STATUS_PENDING, AssessmentTableExportJob::STATUS_PROCESSING]
        );
        if ($running instanceof AssessmentTableExportJob) {
            return $running->getId();
        }

        $job = new AssessmentTableExportJob();
        $job->setProcedureId($procedureId);
        $job->setUserId($userId);
        $job->setParametersHash($parametersHash);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->messageBus->dispatch($createMessage($job->getId()));

        return $job->getId();
    }

    public function createStatusResponse(string $procedureId, string $jobId): JsonResponse
    {
        $job = $this->findOwnJob($procedureId, $jobId);
        if (!$job instanceof AssessmentTableExportJob) {
            return new JsonResponse(['status' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'status' => $job->getStatus(),
            'error'  => $job->getErrorMessage(),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function createDownloadResponse(string $procedureId, string $jobId): StreamedResponse
    {
        $job = $this->findOwnJob($procedureId, $jobId);
        if (!$job instanceof AssessmentTableExportJob || AssessmentTableExportJob::STATUS_COMPLETED !== $job->getStatus()) {
            throw new NotFoundHttpException();
        }

        return $this->downloadResponseFactory->createForJob($job) ?? throw new NotFoundHttpException();
    }

    private function findOwnJob(string $procedureId, string $jobId): ?AssessmentTableExportJob
    {
        $job = $this->entityManager->find(AssessmentTableExportJob::class, $jobId);
        if (!$job instanceof AssessmentTableExportJob
            || $job->getUserId() !== $this->currentUserService->getUser()->getId()
            || $job->getProcedureId() !== $procedureId) {
            return null;
        }

        return $job;
    }
}
