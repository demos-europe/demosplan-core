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
use DemosEurope\DemosplanAddon\Contracts\Exceptions\AddonResourceNotFoundException;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Statement\RecommendationRequestEvent;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FilterUiDataProvider;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureCoupleTokenFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxSegmentImport;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        FileService $fileService,
        PermissionsInterface $permissions,
        Request $request,
        XlsxSegmentImport $importer,
        TranslatorInterface $translator,
        string $procedureId,
    ): Response {
        $requestPost = $request->request->all();
        $procedure = $currentProcedureService->getProcedure();

        if (!$procedure instanceof Procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        // recreate uploaded array
        $uploads = explode(',', (string) $requestPost['uploadedFiles']);

        foreach ($uploads as $uploadHash) {
            $file = $fileService->getFileInfo($uploadHash);
            $fileName = $file->getFileName();
            try {
                $localPath = $fileService->ensureLocalFile($file->getAbsolutePath());
                $localFileInfo = new FileInfo(
                    $file->getHash(),
                    '',
                    0,
                    '',
                    $localPath,
                    $localPath,
                    null
                );
                $result = $importer->importFromFile($localFileInfo);

                if ($result->hasErrors()) {
                    return $this->render(
                        '@DemosPlanCore/DemosPlanProcedure/administration_excel_import_errors.html.twig',
                        [
                            'procedure'  => $procedureId,
                            'context'    => 'segments',
                            'title'      => 'segments.import',
                            'errors'     => $result->getErrorsAsArray(),
                        ]
                    );
                }

                // on success:
                $numberOfCreatedStatements = $result->getStatementCount();
                $numberOfCreatedSegments = $result->getSegmentCount();

                $this->getMessageBag()->add(
                    'confirm',
                    'confirm.segments.imported.from.xlsx',
                    ['%countStatements%' => $numberOfCreatedStatements, '%countSegments%' => $numberOfCreatedSegments, '%fileName%' => $fileName]
                );
                $route = 'dplan_segments_list';
                // Change redirect target if data input user
                if ($permissions->hasPermission('feature_statement_data_input_orga')) {
                    $route = 'DemosPlan_statement_orga_list';
                }

                // cleanup import files
                $fileService->deleteFile($file->getHash());
                $fileService->deleteLocalFile($localPath);

                return $this->redirectToRoute(
                    $route,
                    ['procedureId' => $procedureId]
                );
            } catch (AddonResourceNotFoundException) {
                $this->getMessageBag()->add(
                    'error',
                    'error.import_segment.no_place',
                    [],
                    'messages',
                    'DemosPlan_procedure_places_list',
                    ['procedureId' => $procedureId],
                    $translator->trans('places.addPlace')
                );
            } catch (MissingDataException) {
                $this->getMessageBag()->add('error', 'error.missing.data',
                    ['%fileName%' => $fileName]);
            } catch (Exception $e) {
                $this->logger->error('Unexpected error during document import', [
                    'fileName'  => $fileName,
                    'exception' => $e,
                    'trace'     => $e->getTraceAsString(),
                ]);

                $this->getMessageBag()->add(
                    'error',
                    'statements.import.error.document.unexpected',
                    ['%doc%' => $fileName]
                );
                break;
            }
        }

        return $this->redirectToRoute(
            'DemosPlan_procedure_import',
            ['procedureId' => $procedureId]
        );
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
}
