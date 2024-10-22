<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\XlsxSegmentsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\ORM\Query\QueryException;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use ZipStream\ZipStream;

class SegmentsExportController extends BaseController
{
    private const OUTPUT_DESTINATION = 'php://output';

    public function __construct(
        private readonly NameGenerator $nameGenerator,
        private readonly ProcedureHandler $procedureHandler,
        private readonly RequestStack $requestStack,
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
        StatementHandler $statementHandler,
        FileNameGenerator $fileNameGenerator,
        string $procedureId,
        string $statementId,
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->get('tableHeaders', []);
        $fileNameTemplate = $this->requestStack->getCurrentRequest()->query->get('fileNameTemplate', '');
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $statement = $statementHandler->getStatementWithCertainty($statementId);
        $response = new StreamedResponse(
            static function () use ($procedure, $statement, $exporter, $tableHeaders) {
                $exportedDoc = $exporter->exportForOneStatement($procedure, $statement, $tableHeaders);
                $exportedDoc->save(self::OUTPUT_DESTINATION);
            }
        );

        $this->setResponseHeaders($response, $fileNameGenerator->getFileName($statement, $fileNameTemplate).'.docx');

        return $response;
    }

    /**
     * @throws QueryException
     * @throws UserNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/gruppiert',
        name: 'dplan_statement_segments_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportByStatementsFilterAction(
        FileNameGenerator $fileNameGenerator,
        SegmentsExporter $segmentsExporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        string $procedureId,
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->get('tableHeaders', []);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $requestHandler->getObjectsByQueryParams($this->requestStack->getCurrentRequest()->query, $statementResourceType)->getList()
        );

        $response = new StreamedResponse(
            static function () use ($tableHeaders, $procedure, $statementEntities, $segmentsExporter) {
                $exportedDoc = $segmentsExporter->exportForMultipleStatements($tableHeaders, $procedure, ...$statementEntities);
                $exportedDoc->save(self::OUTPUT_DESTINATION);
            }
        );

        $this->setResponseHeaders($response, $fileNameGenerator->getSynopseFileName($procedure, 'docx'));

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
        FileNameGenerator $fileNameGenerator,
        JsonApiActionService $jsonApiActionService,
        XlsxSegmentsExporter $xlsxSegmentExporter,
        StatementResourceType $statementResourceType,
        string $procedureId,
    ): StreamedResponse {
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $jsonApiActionService->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $statementResourceType
            )->getList()
        );

        $response = new StreamedResponse(
            static function () use ($statementEntities, $xlsxSegmentExporter) {
                $exportedDoc = $xlsxSegmentExporter->exportAllXlsx(...$statementEntities);
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
            $fileNameGenerator->getSynopseFileName($procedure, 'xlsx'))
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
        FileNameGenerator $fileNameGenerator,
        SegmentsExporter $segmentsExporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        ZipExportService $zipExportService,
        string $procedureId,
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->get('tableHeaders', []);
        $fileNameTemplate = $this->requestStack->getCurrentRequest()->query->get('fileNameTemplate', '');
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        // This method applies mostly the same restrictions as the generic API access to retrieve statements.
        // It validates filter and search parameters and limits the returned statement entities to those
        // the user is allowed to see. The actual exporter hardcodes which segments of the statements are included
        // in the export and which properties of the statements and segments are exposed.
        $statementResult = $requestHandler->getObjectsByQueryParams(
            $this->requestStack->getCurrentRequest()->query,
            $statementResourceType
        );
        /** @var Statement[] $statements */
        $statements = array_values($statementResult->getList());
        $statements = $fileNameGenerator->mapStatementsToPathInZip($statements, $fileNameTemplate);

        return $zipExportService->buildZipStreamResponse(
            $fileNameGenerator->getSynopseFileName($procedure, 'zip'),
            static function (ZipStream $zipStream) use ($statements, $segmentsExporter, $zipExportService, $procedure, $tableHeaders): void {
                array_map(
                    static function (
                        Statement $statement,
                        string $filePathInZip,
                    ) use ($segmentsExporter, $zipExportService, $zipStream, $procedure, $tableHeaders): void {
                        $writer = $segmentsExporter->exportForOneStatement($procedure, $statement, $tableHeaders);
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
        string $filename,
    ): void {
        $response->headers->set('Pragma', 'public');
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8'
        );
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($filename));
    }
}
