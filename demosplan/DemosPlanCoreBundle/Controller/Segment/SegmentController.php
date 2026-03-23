<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\RecommendationRequestEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Event\Statement\RecommendationRequestEvent;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FilterUiDataProvider;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureCoupleTokenFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Repository\ImportJobRepository;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SegmentController extends BaseController
{
    #[DplanPermissions('area_statement_segmentation')]
    #[Route(name: 'dplan_segments_list', methods: 'GET', path: '/verfahren/{procedureId}/abschnitte', options: ['expose' => true])]
    public function list(string $procedureId, HashedQueryService $filterSetService): RedirectResponse
    {
        $segmentListQuery = new SegmentListQuery();
        $segmentListQuery->setProcedureId($procedureId);
        $queryHash = $segmentListQuery->getHash();
        $filterSetService->findOrCreateFromQuery($segmentListQuery);

        return $this->redirectToRoute(
            'dplan_segments_list_by_query_hash',
            ['procedureId' => $procedureId, 'queryHash' => $queryHash]
        );
    }

    /**
     * @throws ProcedureNotFoundException
     * @throws StatementNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(name: 'dplan_statement_segments_list', methods: 'GET', path: '/verfahren/{procedureId}/{statementId}/abschnitte', options: ['expose' => true])]
    public function statementSpecificList(
        CurrentUserInterface $currentUser,
        CurrentProcedureService $currentProcedureService,
        ProcedureService $procedureService,
        StatementHandler $statementHandler,
        string $procedureId,
        string $statementId,
        ProcedureCoupleTokenFetcher $tokenFetcher,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $procedure = $procedureService->getProcedure($procedureId);
        $sessionProcedureId = $currentProcedureService->getProcedureIdWithCertainty();
        if ($sessionProcedureId !== $procedureId) {
            throw new BadRequestException('Conflicting procedure IDs in request');
        }

        $statement = $statementHandler->getStatement($statementId);
        if (!$statement instanceof Statement) {
            throw StatementNotFoundException::createFromId($statementId);
        }

        if ($statement->isSegment()) {
            throw StatementNotFoundException::createFromId($statementId);
        }

        if ($procedureId !== $statement->getProcedure()->getId()) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        $recommendationProcedureIds = $procedureService->getRecommendationProcedureIds($currentUser->getUser(), $procedureId);
        $isSourceAndCoupledProcedure = $tokenFetcher->isSourceAndCoupledProcedure($procedure);
        $statementFormDefinition = $procedure->getStatementFormDefinition();
        $eventDispatcher->dispatch(
            new RecommendationRequestEvent($statement, $procedure),
            RecommendationRequestEventInterface::class
        );

        return $this->render(
            '@DemosPlanCore/DemosPlanProcedure/administration_statement_segments_list.html.twig',
            [
                'procedure'                  => [
                    'id'       => $procedureId,
                    'name'     => $procedure->getName(),
                    'orgaName' => $procedure->getOrgaName(),
                ],
                'recommendationProcedureIds' => $recommendationProcedureIds,
                'statementId'                => $statementId,
                'statementExternId'          => $statement->getExternId(),
                'title'                      => 'segments.recommendations.create',
                'templateVars'               => [
                    'isSourceAndCoupledProcedure' => $isSourceAndCoupledProcedure,
                    'statementFormDefinition'     => $statementFormDefinition,
                ],
            ]
        );
    }

    /**
     * Get the position of a segment within its parent statement.
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(name: 'dplan_segment_position', methods: 'GET', path: '/api/segment/{segmentId}/position/{statementId}', options: ['expose' => true])]
    public function getSegmentPosition(
        string $segmentId,
        string $statementId,
        SegmentRepository $segmentRepository,
    ): JsonResponse {
        // Explicit ownership verification: ensure segment belongs to the statement
        $segment = $segmentRepository->find($segmentId);

        if (null === $segment) {
            return new JsonResponse(['error' => 'Segment not found'], Response::HTTP_NOT_FOUND);
        }

        // Verify the segment belongs to the specified statement
        $parentStatement = $segment->getParentStatementOfSegment();
        if ($parentStatement->getId() !== $statementId) {
            return new JsonResponse(
                ['error' => 'Segment does not belong to the specified statement'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Now fetch the position using the repository method
        $position = $segmentRepository->getSegmentPosition($segmentId, $statementId);

        if (null === $position) {
            return new JsonResponse(
                ['error' => 'Unable to calculate segment position'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse($position);
    }

    /**
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('feature_segments_import_excel')]
    #[Route(name: 'dplan_segments_process_import', methods: 'POST', path: '/verfahren/{procedureId}/abschnitte/speichern', options: ['expose' => true])]
    public function importSegmentsFromXlsx(
        CurrentProcedureService $currentProcedureService,
        CurrentUserInterface $currentUser,
        EntityManagerInterface $entityManager,
        FileService $fileService,
        Request $request,
        string $procedureId,
    ): Response {
        $requestPost = $request->request->all();
        $procedure = $currentProcedureService->getProcedure();
        $user = $currentUser->getUser();

        if (!$procedure instanceof Procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        // recreate uploaded array
        $uploads = explode(',', (string) $requestPost['uploadedFiles']);

        foreach ($uploads as $uploadHash) {
            $file = $fileService->getFileInfo($uploadHash);
            $fileName = $file->getFileName();
            $job = new ImportJob();
            try {
                $job->setProcedure($procedure);
                $job->setUser($user);
                $job->setFilePath($uploadHash);
                $job->setFileName($fileName);
                // Capture the current organisation context for background processing
                $currentOrga = $user->getCurrentOrganisation();
                if ($currentOrga instanceof Orga) {
                    $job->setOrganisation($currentOrga);
                }

                $entityManager->persist($job);
                $entityManager->flush();

                $this->logger->info('Import job queued', [
                    'jobId'       => $job->getId(),
                    'fileName'    => $fileName,
                    'procedureId' => $procedureId,
                ]);

                $this->getMessageBag()->add(
                    'confirm',
                    'confirm.segments.import.queued',
                    [
                        '%fileName%' => $fileName,
                        '%jobId%'    => $job->getId(),
                    ]
                );

                // File cleanup happens in ImportJobProcessor after processing
            } catch (Exception $e) {
                $this->logger->error('Failed to queue import job', [
                    'fileName'  => $fileName,
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);

                // Mark job as failed if it was created
                $job->markAsFailed($e->getMessage());
                $entityManager->flush();

                $this->getMessageBag()->add(
                    'error',
                    'error.segments.import.queue.failed',
                    ['%fileName%' => $fileName]
                );
            }
        }

        // Redirect back to import page to show job list
        return $this->redirectToRoute(
            'DemosPlan_procedure_import',
            ['procedureId' => $procedureId]
        );
    }

    /**
     * List all import jobs for a procedure.
     */
    #[DplanPermissions('area_statement_segmentation')]
    #[Route(
        name: 'dplan_import_jobs_list',
        path: '/verfahren/{procedureId}/import/jobs',
        methods: ['GET']
    )]
    public function listImportJobs(
        CurrentProcedureService $currentProcedureService,
        string $procedureId,
    ): Response {
        $procedure = $currentProcedureService->getProcedure();

        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        return $this->render(
            '@DemosPlanCore/DemosPlanSegment/import_jobs_list.html.twig',
            [
                'procedure' => $procedure,
                'title'     => 'import.jobs.list',
            ]
        );
    }

    /**
     * Get import jobs list data (JSON API for Vue component).
     * Returns last 20 jobs only (no pagination needed).
     */
    #[DplanPermissions('area_statement_segmentation')]
    #[Route(
        name: 'dplan_import_jobs_api',
        path: '/verfahren/{procedureId}/import/jobs/api',
        methods: ['GET'],
        options: ['expose' => true]
    )]
    public function getImportJobsApi(
        CurrentProcedureService $currentProcedureService,
        CurrentUserInterface $currentUser,
        ImportJobRepository $importJobRepository,
        string $procedureId,
    ): JsonResponse {
        $procedure = $currentProcedureService->getProcedure();

        if (null === $procedure) {
            return $this->json(['error' => 'Procedure not found'], 404);
        }

        $jobs = $importJobRepository->findJobsForProcedure(
            $procedure,
            $currentUser->getUser()
        );  // Returns last 20 jobs

        $items = array_map(function (ImportJob $job) {
            return [
                'id'             => $job->getId(),
                'fileName'       => $job->getFileName(),
                'status'         => $job->getStatus(),
                'result'         => $job->getResult(),
                'error'          => $job->getError(),
                'createdAt'      => $job->getCreatedAt()->format('Y-m-d H:i:s'),
                'lastActivityAt' => $job->getLastActivityAt()?->format('Y-m-d H:i:s'),
            ];
        }, $jobs);

        return $this->json([
            'items' => $items,
        ]);
    }

    #[DplanPermissions('area_statement_segmentation')]
    #[Route(name: 'dplan_segments_list_by_query_hash', methods: 'GET', path: '/verfahren/{procedureId}/abschnitte/{queryHash}', options: ['expose' => true])]
    public function listFiltered(
        string $procedureId,
        string $queryHash,
        HashedQueryService $filterSetService,
        FilterUiDataProvider $filterUiDataProvider,
    ): Response {
        $querySet = $filterSetService->findHashedQueryWithHash($queryHash);
        $segmentListQuery = $querySet instanceof HashedQuery ? $querySet->getStoredQuery() : null;
        if (!$segmentListQuery instanceof SegmentListQuery) {
            throw BadRequestException::unknownQueryHash($queryHash);
        }

        $filter = $segmentListQuery->getFilter();
        $filterNames = $filterUiDataProvider->getFilterNames();
        $filterNames = $filterUiDataProvider->addSelectedField($filterNames, $filter);

        return $this->render(
            '@DemosPlanCore/DemosPlanProcedure/administration_segments_list.html.twig',
            [
                'filterNames'      => $filterNames,
                'procedureId'      => $procedureId,
                'segmentListQuery' => $segmentListQuery,
                'title'            => 'segments',
            ]
        );
    }

    #[DplanPermissions('area_statement_segmentation')]
    #[Route(name: 'dplan_segment_delete', path: '/verfahren/{procedureId}/abschnitt/{segmentId}/delete', options: ['expose' => true])]
    public function deleteSegmentAction(
        string $procedureId,
        string $segmentId,
        SegmentHandler $segmentHandler,
        MessageBagInterface $messageBag,
    ): Response {
        $success = $segmentHandler->delete($segmentId);

        if ($success) {
            $messageBag->add('confirm', 'confirm.segment.deleted');
        } else {
            $messageBag->add('error', 'error.segment.delete.failed');
        }

        return $this->redirectToRoute(
            'dplan_procedure_statement_list',
            ['procedureId' => $procedureId]
        );
    }
}
