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
use demosplan\DemosPlanCoreBundle\Exception\IncompleteSegmentMarkersException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Exception\MalformedDocxException;
use demosplan\DemosPlanCoreBundle\Exception\MissingSegmentBlockException;
use demosplan\DemosPlanCoreBundle\Exception\SegmentDataOutsideBlockException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UnknownPlaceholdersException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFingerprint;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Export\ProcedureExportJobService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentsExportResponseBuilder;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Message\ExportSegmentsMessage;
use Doctrine\ORM\Query\QueryException;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsExportController extends BaseController
{
    private const OUTPUT_DESTINATION = 'php://output';
    private const UPLOADED_TEMPLATE_HASH = 'uploadedDocxTemplate';
    private const DOCX_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const DOCX_EXTENSION = '.docx';

    public function __construct(
        private readonly NameGenerator $nameGenerator,
        private readonly ProcedureHandler $procedureHandler,
        private readonly RequestStack $requestStack,
        private readonly SegmentsExportResponseBuilder $responseBuilder,
        private readonly StatementExportTagFilter $statementExportTagFilter,
        private readonly TranslatorInterface $translator,
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
    public function export(
        SegmentsByStatementsExporter $segmentsExporter,
        StatementHandler $statementHandler,
        FileNameGenerator $fileNameGenerator,
        string $procedureId,
        string $statementId,
    ): StreamedResponse {
        $query = $this->requestStack->getCurrentRequest()->query;
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $query->all(SegmentsExportResponseBuilder::TABLE_HEADERS_PARAMETER);
        $fileNameTemplate = $query->get(SegmentsExportResponseBuilder::FILE_NAME_TEMPLATE_PARAMETER, '');
        $isObscure = $this->responseBuilder->getBooleanParameter($query, SegmentsExportResponseBuilder::OBSCURE_PARAMETER);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $statement = $statementHandler->getStatementWithCertainty($statementId);
        $censorCitizenData = $this->responseBuilder->getBooleanParameter($query, SegmentsExportResponseBuilder::CITIZEN_CENSOR_PARAMETER);
        $censorInstitutionData = $this->responseBuilder->getBooleanParameter($query, SegmentsExportResponseBuilder::INSTITUTION_CENSOR_PARAMETER);

        $response = new StreamedResponse(
            static function () use ($procedure, $statement, $segmentsExporter, $tableHeaders, $censorCitizenData, $censorInstitutionData, $isObscure) {
                $exportedDoc = $segmentsExporter->export(
                    $procedure,
                    $statement,
                    $tableHeaders,
                    $censorCitizenData,
                    $censorInstitutionData,
                    $isObscure
                );
                $exportedDoc->save(self::OUTPUT_DESTINATION);
            }
        );

        $this->responseBuilder->setDocxResponseHeaders($response, $fileNameGenerator->getFileName($statement, $fileNameTemplate).self::DOCX_EXTENSION);

        return $response;
    }

    /**
     * Renders a planner-uploaded DOCX layout template against the segments of a
     * single statement and streams the populated DOCX back. The template is
     * resolved via TUS hash (`uploadedDocxTemplate` query parameter) and
     * removed from local disk as soon as the response finishes (success or
     * failure).
     */
    #[DplanPermissions('feature_statement_via_template_export')]
    #[Route(
        path: '/verfahren/{procedureId}/{statementId}/abschnitte/export/vorlage',
        name: 'dplan_statement_via_template_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportViaTemplate(
        FileService $fileService,
        FileNameGenerator $fileNameGenerator,
        StatementHandler $statementHandler,
        StatementViaTemplateExporter $exporter,
        string $procedureId,
        string $statementId,
    ): StreamedResponse|RedirectResponse {
        $request = $this->requestStack->getCurrentRequest();
        $absolutePath = null;
        try {
            $uploadedTemplateHash = $request->query->get(self::UPLOADED_TEMPLATE_HASH);
            if (null === $uploadedTemplateHash || '' === $uploadedTemplateHash) {
                throw new MalformedDocxException('Invalid template hash provided.');
            }

            $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
            $statement = $statementHandler->getStatementWithCertainty($statementId);

            if (self::DOCX_MIME_TYPE !== $fileService->getFileInfo($uploadedTemplateHash)->getContentType()) {
                throw new MalformedDocxException('Invalid mime type provided, only docx allowed.');
            }

            $absolutePath = $fileService->ensureLocalFileFromHash($uploadedTemplateHash);
            $templateProcessor = $exporter->export($procedure, $statement, $absolutePath);
            $fileNameTemplate = $request->query->get(SegmentsExportResponseBuilder::FILE_NAME_TEMPLATE_PARAMETER, '')
                ?: FileNameGenerator::PLACEHOLDER_ID.'-'.FileNameGenerator::PLACEHOLDER_NAME;

            $response = new StreamedResponse(
                static function () use ($templateProcessor): void {
                    $templateProcessor->saveAs(self::OUTPUT_DESTINATION);
                }
            );

            $this->responseBuilder->setDocxResponseHeaders(
                $response,
                $fileNameGenerator->getFileName($statement, $fileNameTemplate).self::DOCX_EXTENSION
            );

            return $response;
        } catch (InvalidStatementTemplateException $exception) {
            $this->logger->warning('Statement template export rejected', ['exception' => $exception]);
            $this->getMessageBag()->add('error', $this->translateTemplateException($exception));

            return $this->redirectBack($request);
        } catch (Exception $exception) {
            $this->logger->error('Unexpected error during statement template export', ['exception' => $exception]);
            $this->getMessageBag()->add('error', $this->translator->trans('error.generic'));

            return $this->redirectBack($request);
        } finally {
            if (null !== $absolutePath) {
                $fileService->deleteLocalFile($absolutePath);
            }
        }
    }

    private function translateTemplateException(InvalidStatementTemplateException $exception): string
    {
        $key = match (true) {
            $exception instanceof UnknownPlaceholdersException      => 'docx.export.via_template.error.unknown_placeholder',
            $exception instanceof IncompleteSegmentMarkersException => 'docx.export.via_template.error.segments_marker_incomplete',
            $exception instanceof MissingSegmentBlockException      => 'docx.export.via_template.error.segment_data_without_block',
            $exception instanceof SegmentDataOutsideBlockException  => 'docx.export.via_template.error.segment_data_outside_block',
            default                                                 => 'docx.export.via_template.error.malformed_docx',
        };

        $parameters = $exception instanceof UnknownPlaceholdersException
            ? ['placeholders' => implode(', ', $exception->getUnknownPlaceholders())]
            : [];

        return $this->translator->trans($key, $parameters);
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
    public function exportByStatementsFilter(
        string $procedureId,
    ): StreamedResponse {
        return $this->responseBuilder->build(
            $this->procedureHandler->getProcedureWithCertainty($procedureId),
            $this->requestStack->getCurrentRequest()->query,
            SegmentsExportResponseBuilder::TYPE_DOCX
        );
    }

    /**
     * Start an asynchronous grouped segments export. Instead of building the file inside the web
     * request (which times out on large procedures), this enqueues a background job and returns
     * its id so the browser can poll for completion and then download the result.
     *
     * @throws Exception
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/gruppiert/async',
        name: 'dplan_statement_segments_export_async_start',
        options: ['expose' => true],
        methods: ['POST'],
        defaults: ['exportType' => SegmentsExportResponseBuilder::TYPE_DOCX]
    )]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/gepackt/async',
        name: 'dplan_statement_segments_export_packaged_async_start',
        options: ['expose' => true],
        methods: ['POST'],
        defaults: ['exportType' => SegmentsExportResponseBuilder::TYPE_ZIP]
    )]
    public function startAsyncExport(
        CurrentUserService $currentUserService,
        CustomerService $customerService,
        ProcedureExportJobService $exportJobService,
        string $procedureId,
        string $exportType,
    ): Response {
        $queryParams = $this->requestStack->getCurrentRequest()->query->all();

        $userId = $currentUserService->getUser()->getId();
        $jobId = $exportJobService->start(
            $userId,
            $procedureId,
            ExportJobFingerprint::forSegmentsExport($exportType, $queryParams),
            static fn (string $jobId): ExportSegmentsMessage => new ExportSegmentsMessage(
                $jobId,
                $exportType,
                $procedureId,
                $userId,
                $customerService->getCurrentCustomer()->getId(),
                $queryParams
            )
        );

        return new JsonResponse(['jobId' => $jobId]);
    }

    /**
     * Poll the status of an asynchronous grouped segments export.
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/status/{jobId}',
        name: 'dplan_statement_segments_export_status',
        options: ['expose' => true],
        methods: ['GET']
    )]
    public function exportStatus(
        ProcedureExportJobService $exportJobService,
        string $procedureId,
        string $jobId,
    ): Response {
        return $exportJobService->createStatusResponse($procedureId, $jobId);
    }

    /**
     * Download the result of a finished asynchronous grouped segments export.
     */
    #[DplanPermissions('feature_segments_of_statement_list')]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/download/{jobId}',
        name: 'dplan_statement_segments_export_download',
        options: ['expose' => true],
        methods: ['GET']
    )]
    public function exportDownload(
        ProcedureExportJobService $exportJobService,
        string $procedureId,
        string $jobId,
    ): Response {
        return $exportJobService->createDownloadResponse($procedureId, $jobId);
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
    public function exportByStatementsFilterXls(
        SegmentsByStatementsExporter $exporter,
        string $procedureId,
    ): StreamedResponse {
        $query = $this->requestStack->getCurrentRequest()->query;
        $statementEntities = $this->responseBuilder->resolveStatements($procedureId, $query);

        $response = new StreamedResponse(
            function () use ($statementEntities, $exporter) {
                $exportedDoc = $exporter->exportAllXlsx(
                    $this->statementExportTagFilter,
                    ...$statementEntities
                );
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
        $fileName = $this->responseBuilder->getSynopseFileName($procedure, $query, 'xlsx');
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($fileName));

        return $response;
    }

    /**
     * @throws UserNotFoundException
     * @throws QueryException
     * @throws Exception
     */
    #[DplanPermissions(
        'feature_statement_segments_export_csv'
    )]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/csv',
        name: 'dplan_statement_csv_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportByStatementsFilterCsv(
        SegmentsByStatementsExporter $exporter,
        string $procedureId,
    ): StreamedResponse {
        $query = $this->requestStack->getCurrentRequest()->query;
        $statementEntities = $this->responseBuilder->resolveStatements($procedureId, $query);

        $response = new StreamedResponse(
            static function () use ($statementEntities, $exporter) {
                echo $exporter->exportAllCsv(...$statementEntities);
            }
        );

        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');

        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $fileName = $this->responseBuilder->getSynopseFileName($procedure, $query, 'csv');
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($fileName));

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
    public function exportPackagedStatements(
        string $procedureId,
    ): StreamedResponse {
        // This method applies mostly the same restrictions as the generic API access to retrieve statements.
        // It validates filter and search parameters and limits the returned statement entities to those
        // the user is allowed to see. The actual exporter hardcodes which segments of the statements are included
        // in the export and which properties of the statements and segments are exposed.
        return $this->responseBuilder->build(
            $this->procedureHandler->getProcedureWithCertainty($procedureId),
            $this->requestStack->getCurrentRequest()->query,
            SegmentsExportResponseBuilder::TYPE_ZIP
        );
    }

}
