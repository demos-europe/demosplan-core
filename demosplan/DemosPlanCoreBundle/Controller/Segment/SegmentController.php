<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FilterUiDataProvider;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxSegmentImport;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use demosplan\DemosPlanStatementBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SegmentController extends BaseController
{
    /**
     * @Route(
     *     name="dplan_segments_list",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/abschnitte",
     *     options={"expose": true})
     * @DplanPermissions("area_statement_segmentation")
     */
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
     * @Route(name="dplan_statement_segments_list",
     *        methods="GET",
     *        path="/verfahren/{procedureId}/{statementId}/abschnitte",
     *        options={"expose": true})
     * @DplanPermissions("feature_segments_of_statement_list")
     *
     * @throws ProcedureNotFoundException
     * @throws StatementNotFoundException
     * @throws Exception
     */
    public function statementSpecificListAction(
        CurrentUserInterface $currentUser,
        CurrentProcedureService $currentProcedureService,
        ProcedureService $procedureService,
        StatementHandler $statementHandler,
        string $procedureId,
        string $statementId
    ): Response {
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

        return $this->renderTemplate(
            '@DemosPlanProcedure/DemosPlanProcedure/administration_statement_segments_list.html.twig',
            [
                'procedure'                  => $procedureId,
                'recommendationProcedureIds' => $recommendationProcedureIds,
                'statementId'                => $statementId,
                'statementExternId'          => $statement->getExternId(),
                'title'                      => 'segments.recommendations.create',
            ]
        );
    }

    /**
     * @Route(
     *     name="dplan_segments_process_import",
     *     methods="POST",
     *     path="/verfahren/{procedureId}/abschnitte/speichern",
     *     options={"expose": true})
     * @DplanPermissions("feature_segments_import_excel")
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    public function importSegmentsFromXlsx(
        CurrentProcedureService $currentProcedureService,
        FileService $fileService,
        PermissionsInterface $permissions,
        Request $request,
        XlsxSegmentImport $importer,
        string $procedureId
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
                $result = $importer->importFromFile($file);

                if ($result->hasErrors()) {
                    return $this->renderTemplate(
                        '@DemosPlanProcedure/DemosPlanProcedure/administration_excel_import_errors.html.twig',
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

                return $this->redirectToRoute(
                    $route,
                    compact('procedureId')
                );
            } catch (MissingDataException $exception) {
                $this->getMessageBag()->add('error', 'error.missing.data',
                    ['%fileName%' => $fileName]);
            } catch (Exception $exception) {
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
     * @Route(
     *     name="dplan_segments_list_by_query_hash",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/abschnitte/{queryHash}",
     *     options={"expose": true})
     * @DplanPermissions("area_statement_segmentation")
     */
    public function listFilteredAction(
        string $procedureId,
        string $queryHash,
        HashedQueryService $filterSetService,
        FilterUiDataProvider $filterUiDataProvider
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
            '@DemosPlanProcedure/DemosPlanProcedure/administration_segments_list.html.twig',
            [
                'filterNames'      => $filterNames,
                'procedureId'      => $procedureId,
                'segmentListQuery' => $segmentListQuery,
                'title'            => 'segments',
            ]
        );
    }
}
