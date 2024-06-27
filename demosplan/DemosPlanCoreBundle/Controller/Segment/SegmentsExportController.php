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
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\ORM\Query\QueryException;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use ZipStream\ZipStream;

class SegmentsExportController extends BaseController
{
    private const OUTPUT_DESTINATION = 'php://output';

    public function __construct(
        private readonly Request $request,
        private readonly NameGenerator $nameGenerator,
        private readonly ProcedureHandler $procedureHandler,
    ) {
    }

    /**
     * @throws StatementNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(
        path: '/verfahren/{procedureId}/{statementId}/abschnitte/export',
        name: 'dplan_segments_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportAction(
        SegmentsExporter $exporter,
        Slugify $slugify,
        StatementHandler $statementHandler,
        string $procedureId,
        string $statementId
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->request->query->get('tableHeaders', []);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $statement = $statementHandler->getStatementWithCertainty($statementId);
        $response = new StreamedResponse(
            static function () use ($procedure, $statement, $exporter, $tableHeaders) {
                $exportedDoc = $exporter->export($procedure, $statement, $tableHeaders);
                $exportedDoc->save(self::OUTPUT_DESTINATION);
            }
        );

        $filename = $slugify->slugify($procedure->getName())
            .'-'
            .$statement->getExternId().'.docx';

        $this->setResponseHeaders($response, $filename);

        return $response;
    }

    /**
     * @throws QueryException
     * @throws UserNotFoundException
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/gruppiert',
        name: 'dplan_statement_segments_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportByStatementsFilterAction(
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        string $procedureId
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->request->query->get('tableHeaders', []);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $requestHandler->getObjectsByQueryParams($this->request->query, $statementResourceType)->getList()
        );

        $response = new StreamedResponse(
            static function () use ($tableHeaders, $procedure, $statementEntities, $exporter) {
                $exportedDoc = $exporter->exportAll($tableHeaders, $procedure, ...$statementEntities);
                $exportedDoc->save(self::OUTPUT_DESTINATION);
            }
        );

        $this->setResponseHeaders($response, $exporter->getSynopseFileName($procedure, 'docx'));

        return $response;
    }

    /**
     * @throws UserNotFoundException
     * @throws QueryException
     * @throws Exception
     */
    #[DplanPermissions(
        'feature_admin_assessmenttable_export_statement_generic_xlsx'
    )]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/xlsx',
        name: 'dplan_statement_xls_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportByStatementsFilterXlsAction(
        JsonApiActionService $jsonApiActionService,
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        string $procedureId
    ): StreamedResponse {
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $jsonApiActionService->getObjectsByQueryParams($this->request->query, $statementResourceType)->getList()
        );

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

        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename(
            $exporter->getSynopseFileName($procedure, 'xlsx'))
        );

        return $response;
    }

    /**
     * @throws QueryException
     * @throws UserNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(path: '/verfahren/{procedureId}/abschnitte/export/gepackt',
        name: 'dplan_statement_segments_export_packaged',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportPackagedStatementsAction(
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        ZipExportService $zipExportService,
        string $procedureId
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->request->query->get('tableHeaders', []);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        // Using this method we apply mostly the same restrictions that are applied when the generic
        // API is accessed to retrieve statements. Things like filter and search parameters are
        // validated and the returned statement entities limited to such that the user is allowed to
        // see. However, what segments of the statements are included in the export and what
        // properties of the statements and segments are exposed is hardcoded by the actual
        // exporter.
        $statementResult = $requestHandler->getObjectsByQueryParams($this->request->query, $statementResourceType);
        /** @var Statement[] $statements */
        $statements = array_values($statementResult->getList());
        $statements = $exporter->mapStatementsToPathInZip($statements);

        return $zipExportService->buildZipStreamResponse(
            $exporter->getSynopseFileName($procedure, 'zip'),
            static function (ZipStream $zipStream) use ($statements, $exporter, $zipExportService, $procedure, $tableHeaders): void {
                array_map(
                    static function (
                        Statement $statement,
                        string $filePathInZip
                    ) use ($exporter, $zipExportService, $zipStream, $procedure, $tableHeaders): void {
                        $docx = $exporter->exportStatementSegmentsInSeparateDocx($statement, $procedure, $tableHeaders);
                        $writer = IOFactory::createWriter($docx);
                        $zipExportService->addWriterToZipStream(
                            $writer,
                            $filePathInZip,
                            $zipStream,
                            'statement_segments_zip_export',
                            '.docx'
                        );
                    },
                    $statements,
                    array_keys($statements)
                );
            }
        );
    }

    private function setResponseHeaders(
        StreamedResponse $response,
        string $filename
    ): void {
        $response->headers->set('Pragma', 'public');
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8'
        );
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($filename));
    }
}
