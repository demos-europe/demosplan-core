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
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
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
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SegmentController extends BaseController
{
    /**
     * @DplanPermissions("area_statement_segmentation")
     */
    #[Route(name: 'dplan_segments_list', methods: 'GET', path: '/verfahren/{procedureId}/abschnitte', options: ['expose' => true])]
    public function listAction(string $procedureId, HashedQueryService $filterSetService): RedirectResponse
    {
        $segmentListQuery = new SegmentListQuery();
        $segmentListQuery->setProcedureId($procedureId);
        $queryHash = $segmentListQuery->getHash();
        $filterSetService->findOrCreateFromQuery($segmentListQuery);

        return $this->redirectToRoute(
            'dplan_segments_list_by_query_hash',
            compact('procedureId', 'queryHash')
        );
    }

    /**
     * @DplanPermissions("feature_segments_of_statement_list")
     *
     * @throws ProcedureNotFoundException
     * @throws StatementNotFoundException
     * @throws Exception
     */
    #[Route(name: 'dplan_statement_segments_list', methods: 'GET', path: '/verfahren/{procedureId}/{statementId}/abschnitte', options: ['expose' => true])]
    public function statementSpecificListAction(
        CurrentUserInterface $currentUser,
        CurrentProcedureService $currentProcedureService,
        ProcedureService $procedureService,
        StatementHandler $statementHandler,
        string $procedureId,
        string $statementId,
        ProcedureCoupleTokenFetcher $tokenFetcher,
    ): Response {
        $procedure = $procedureService->getProcedure($procedureId);
        $sessionProcedureId = $currentProcedureService->getProcedureIdWithCertainty();
        if ($sessionProcedureId !== $procedureId) {
            throw new BadRequestException('Conflicting procedure IDs in request');
        }

        $statement = $statementHandler->getStatement($statementId);
        if (null === $statement) {
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

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_statement_segments_list.html.twig',
            [
                'procedure'                  => $procedureId,
                'recommendationProcedureIds' => $recommendationProcedureIds,
                'statementId'                => $statementId,
                'statementExternId'          => $statement->getExternId(),
                'title'                      => 'segments.recommendations.create',
                'templateVars'               => [
                    'isSourceAndCoupledProcedure' => $isSourceAndCoupledProcedure,
                ],
            ]
        );
    }

    /**
     * @DplanPermissions("feature_segments_import_excel")
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    #[Route(name: 'dplan_segments_process_import', methods: 'POST', path: '/verfahren/{procedureId}/abschnitte/speichern', options: ['expose' => true])]
    public function importSegmentsFromXlsx(
        CurrentProcedureService $currentProcedureService,
        FileService $fileService,
        PermissionsInterface $permissions,
        Request $request,
        XlsxSegmentImport $importer,
        string $procedureId,
    ): Response {
        $requestPost = $request->request->all();
        $procedure = $currentProcedureService->getProcedure();

        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        // recreate uploaded array
        $uploads = explode(',', $requestPost['uploadedFiles']);

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
                    return $this->renderTemplate(
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
                    compact('procedureId')
                );
            } catch (MissingDataException) {
                $this->getMessageBag()->add('error', 'error.missing.data',
                    ['%fileName%' => $fileName]);
            } catch (Exception) {
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
            compact('procedureId')
        );
    }

    /**
     * @DplanPermissions("area_statement_segmentation")
     */
    #[Route(name: 'dplan_segments_list_by_query_hash', methods: 'GET', path: '/verfahren/{procedureId}/abschnitte/{queryHash}', options: ['expose' => true])]
    public function listFilteredAction(
        string $procedureId,
        string $queryHash,
        HashedQueryService $filterSetService,
        FilterUiDataProvider $filterUiDataProvider,
    ): Response {
        $querySet = $filterSetService->findHashedQueryWithHash($queryHash);
        $segmentListQuery = null === $querySet ? null : $querySet->getStoredQuery();
        if (!$segmentListQuery instanceof SegmentListQuery) {
            throw BadRequestException::unknownQueryHash($queryHash);
        }

        $filter = $segmentListQuery->getFilter();
        $filterNames = $filterUiDataProvider->getFilterNames();
        $filterNames = $filterUiDataProvider->addSelectedField($filterNames, $filter);

        return $this->renderTemplate(
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
