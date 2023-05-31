<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use Cocur\Slugify\Slugify;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use ZipStream\ZipStream;

class SegmentsExportController extends BaseController
{
    /**
     * @Route(
     *     name="dplan_segments_export",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/{statementId}/abschnitte/export",
     *     options={"expose": true})
     *
     * @throws StatementNotFoundException
     * @throws Exception
     *
     * @DplanPermissions("feature_segments_of_statement_list")
     */
    public function exportAction(
        ProcedureHandler $procedureHandler,
        SegmentsExporter $exporter,
        Slugify $slugify,
        StatementHandler $statementHandler,
        string $procedureId,
        string $statementId
    ): StreamedResponse {
        $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);
        $statement = $statementHandler->getStatementWithCertainty($statementId);
        $response = new StreamedResponse(
            static function () use ($procedure, $statement, $exporter) {
                $exportedDoc = $exporter->export($procedure, $statement);
                $exportedDoc->save('php://output');
            }
        );

        $filename = $slugify->slugify($procedure->getName())
                    .'-'
                    .$statement->getExternId().'.docx';

        $this->setResponseHeaders($response, $filename);

        return $response;
    }

    /**
     * @Route(
     *     name="dplan_statement_segments_export",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/abschnitte/export/gruppiert",
     *     options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_segments_of_statement_list")
     */
    public function exportByStatementsFilterAction(
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        ProcedureHandler $procedureHandler,
        Request $request,
        string $procedureId
    ): StreamedResponse {
        $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values($requestHandler->getObjectsByQueryParams($request->query, $statementResourceType)->getList());

        $response = new StreamedResponse(
            static function () use ($procedure, $statementEntities, $exporter) {
                $exportedDoc = $exporter->exportAll($procedure, ...$statementEntities);
                $exportedDoc->save('php://output');
            }
        );

        $this->setResponseHeaders($response, $exporter->getSynopseFileName($procedure, 'docx'));

        return $response;
    }

    /**
     * @Route(
     *     name="dplan_statement_xls_export",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/abschnitte/export/xlsx",
     *     options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_admin_assessmenttable_export_statement_generic_xlsx")
     */
    public function exportByStatementsFilterXlsAction(
        JsonApiActionService $jsonApiActionService,
        ProcedureHandler $procedureHandler,
        Request $request,
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        string $procedureId
    ): StreamedResponse {
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values($jsonApiActionService->getObjectsByQueryParams($request->query, $statementResourceType)->getList());

        $response = new StreamedResponse(
            static function () use ($statementEntities, $exporter) {
                $exportedDoc = $exporter->exportAllXlsx(...$statementEntities);
                $exportedDoc->save('php://output');
            }
        );

        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8'
        );

        $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);
        $response->headers->set('Content-Disposition', $this->generateDownloadFilename(
            $exporter->getSynopseFileName($procedure, 'xlsx'))
        );

        return $response;
    }

    /**
     * @Route(
     *     name="dplan_statement_segments_export_packaged",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/abschnitte/export/gepackt",
     *     options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_segments_of_statement_list")
     */
    public function exportPackagedStatementsAction(
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        ProcedureHandler $procedureHandler,
        Request $request,
        ZipExportService $zipExportService,
        string $procedureId
    ): StreamedResponse {
        $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);
        // Using this method we apply mostly the same restrictions that are applied when the generic
        // API is accessed to retrieve statements. Things like filter and search parameters are
        // validated and the returned statement entities limited to such that the user is allowed to
        // see. However, what segments of the statements are included in the export and what
        // properties of the statements and segments are exposed is hardcoded by the actual
        // exporter.
        $statementResult = $requestHandler->getObjectsByQueryParams($request->query, $statementResourceType);
        $statements = array_values($statementResult->getList());
        $statements = $exporter->mapStatementsToPathInZip($statements);

        return $zipExportService->buildZipStreamResponse(
            $exporter->getSynopseFileName($procedure, 'zip'),
            static function (ZipStream $zipStream) use ($statements, $exporter, $zipExportService, $procedure): void {
                array_map(static function (Statement $statement, string $filePathInZip) use ($exporter, $zipExportService, $zipStream, $procedure): void {
                    $docx = $exporter->exportStatementSegmentsInSeparateDocx($statement, $procedure);
                    $writer = IOFactory::createWriter($docx);
                    $zipExportService->addWriterToZipStream(
                        $writer,
                        $filePathInZip,
                        $zipStream,
                        'statement_segments_zip_export',
                        '.docx'
                    );
                }, $statements, array_keys($statements));
            }
        );
    }

    private function setResponseHeaders(StreamedResponse $response, string $filename): void
    {
        $response->headers->set('Pragma', 'public');
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8'
        );
        $response->headers->set('Content-Disposition', $this->generateDownloadFilename($filename));
    }
}
