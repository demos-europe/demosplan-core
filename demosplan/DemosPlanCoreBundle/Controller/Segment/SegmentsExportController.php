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
use demosplan\DemosPlanCoreBundle\Exception\IncompleteSegmentMarkersException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Exception\MalformedDocxException;
use demosplan\DemosPlanCoreBundle\Exception\MissingSegmentBlockException;
use demosplan\DemosPlanCoreBundle\Exception\SegmentDataOutsideBlockException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UnknownPlaceholdersException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Export\StatementZipPathResolver;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\ORM\Query\QueryException;
use EDT\JsonApi\RequestHandling\UrlParameter;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use ZipStream\ZipStream;

class SegmentsExportController extends BaseController
{
    private const OUTPUT_DESTINATION = 'php://output';
    private const TABLE_HEADERS_PARAMETER = 'tableHeaders';
    private const FILE_NAME_TEMPLATE_PARAMETER = 'fileNameTemplate';
    private const CITIZEN_CENSOR_PARAMETER = 'isCitizenDataCensored';
    private const INSTITUTION_CENSOR_PARAMETER = 'isInstitutionDataCensored';
    private const OBSCURE_PARAMETER = 'isObscured';
    private const CUSTOM_HEADER_TEXT_PARAMETER = 'customHeaderText';
    private const UPLOADED_TEMPLATE_HASH = 'uploadedDocxTemplate';
    private const DOCX_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const DOCX_EXTENSION = '.docx';

    public function __construct(
        private readonly NameGenerator $nameGenerator,
        private readonly ProcedureHandler $procedureHandler,
        private readonly RequestStack $requestStack,
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
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->all(self::TABLE_HEADERS_PARAMETER);
        $fileNameTemplate = $this->requestStack->getCurrentRequest()->query->get(self::FILE_NAME_TEMPLATE_PARAMETER, '');
        $isObscure = $this->getBooleanQueryParameter(self::OBSCURE_PARAMETER);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $statement = $statementHandler->getStatementWithCertainty($statementId);
        $censorCitizenData = $this->getBooleanQueryParameter(self::CITIZEN_CENSOR_PARAMETER);
        $censorInstitutionData = $this->getBooleanQueryParameter(self::INSTITUTION_CENSOR_PARAMETER);

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

        $this->setResponseHeaders($response, $fileNameGenerator->getFileName($statement, $fileNameTemplate).self::DOCX_EXTENSION);

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
            $fileNameTemplate = $request->query->get(self::FILE_NAME_TEMPLATE_PARAMETER, '')
                ?: FileNameGenerator::PLACEHOLDER_ID.'-'.FileNameGenerator::PLACEHOLDER_NAME;

            $response = new StreamedResponse(
                static function () use ($templateProcessor): void {
                    $templateProcessor->saveAs(self::OUTPUT_DESTINATION);
                }
            );

            $this->setResponseHeaders(
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
        FileNameGenerator $fileNameGenerator,
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        string $procedureId,
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->all(self::TABLE_HEADERS_PARAMETER);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);

        // Push the tag filter into the query so only statements carrying a matching tag are
        // loaded, instead of loading every statement of the procedure and discarding the rest
        // in PHP.
        $tagsFilter = $this->requestStack->getCurrentRequest()->query->all('tagsFilter');
        $tagConditions = $this->statementExportTagFilter->buildStatementTagConditions($tagsFilter, $statementResourceType, $procedureId);

        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $requestHandler->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $statementResourceType,
                $tagConditions
            )->getList()
        );

        $noTagsFilter = $this->requestStack->getCurrentRequest()->query->all(UrlParameter::FILTER);

        // Trim each loaded statement to only its matching segments and collect the matched tag
        // titles for the export header. Runs on the already-narrowed statement set.
        $statementEntities = $this->statementExportTagFilter->filterStatementsByTags($statementEntities, $tagsFilter);
        $exportFilteredByTagsWithTopics = $this->statementExportTagFilter->getFilteredTagsWithTitles();
        $customHeaderText = $this->requestStack->getCurrentRequest()->query->get(self::CUSTOM_HEADER_TEXT_PARAMETER) ?? '';

        $censorCitizenData = $this->getBooleanQueryParameter(self::CITIZEN_CENSOR_PARAMETER);
        $censorInstitutionData = $this->getBooleanQueryParameter(self::INSTITUTION_CENSOR_PARAMETER);
        // geschwärzt
        $obscureParameter = $this->getBooleanQueryParameter(self::OBSCURE_PARAMETER);

        $response = new StreamedResponse(
            function () use (
                $tableHeaders,
                $procedure,
                $statementEntities,
                $exporter,
                $censorCitizenData,
                $censorInstitutionData,
                $obscureParameter,
                $exportFilteredByTagsWithTopics,
                $customHeaderText
            ) {
                $exportedDoc = $exporter->exportAll(
                    $tableHeaders,
                    $procedure,
                    $obscureParameter,
                    $exportFilteredByTagsWithTopics,
                    $censorCitizenData,
                    $censorInstitutionData,
                    $customHeaderText,
                    ...$statementEntities
                );
                $exportedDoc->save(self::OUTPUT_DESTINATION);
            }
        );
        // generating file name based on it being filtered by tags or not
        0 === count($tagsFilter) && 0 === count($noTagsFilter) ?
            $this->setResponseHeaders($response, $fileNameGenerator->getSynopseFileName($procedure, 'docx')) : $this->setResponseHeaders($response, $fileNameGenerator->getFilteredSynopseFileName($procedure, 'docx'));

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
    public function exportByStatementsFilterXls(
        FileNameGenerator $fileNameGenerator,
        JsonApiActionService $jsonApiActionService,
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        string $procedureId,
    ): StreamedResponse {
        // Push the tag filter into the query so only statements carrying a matching tag are
        // loaded, instead of loading every statement of the procedure and discarding the rest
        // in PHP.
        $tagsFilter = $this->requestStack->getCurrentRequest()->query->all('tagsFilter');
        $tagConditions = $this->statementExportTagFilter->buildStatementTagConditions($tagsFilter, $statementResourceType, $procedureId);

        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $jsonApiActionService->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $statementResourceType,
                $tagConditions
            )->getList()
        );

        // Trim each loaded statement to only its matching segments. Runs on the already-narrowed set.
        $statementEntities = $this->statementExportTagFilter->filterStatementsByTags($statementEntities, $tagsFilter);

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
        // generating file name based on it being a filtered export or not
        $noTagsFilter = $this->requestStack->getCurrentRequest()->query->all(UrlParameter::FILTER);
        $fileName = 0 === count($tagsFilter) && 0 === count($noTagsFilter) ? $fileNameGenerator->getSynopseFileName($procedure, 'xlsx') : $fileNameGenerator->getFilteredSynopseFileName($procedure, 'xlsx');
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
        FileNameGenerator $fileNameGenerator,
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        StatementZipPathResolver $zipPathResolver,
        ZipExportService $zipExportService,
        string $procedureId,
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->all(self::TABLE_HEADERS_PARAMETER);
        $fileNameTemplate = $this->requestStack->getCurrentRequest()->query->get(self::FILE_NAME_TEMPLATE_PARAMETER, '');

        $censorInstitutionData = $this->getBooleanQueryParameter(self::INSTITUTION_CENSOR_PARAMETER);
        $censorCitizenData = $this->getBooleanQueryParameter(self::CITIZEN_CENSOR_PARAMETER);
        $obscureParameter = $this->getBooleanQueryParameter(self::OBSCURE_PARAMETER);
        $customHeaderText = $this->requestStack->getCurrentRequest()->query->get(self::CUSTOM_HEADER_TEXT_PARAMETER) ?? '';

        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        // This method applies mostly the same restrictions as the generic API access to retrieve statements.
        // It validates filter and search parameters and limits the returned statement entities to those
        // the user is allowed to see. The actual exporter hardcodes which segments of the statements are included
        // in the export and which properties of the statements and segments are exposed.
        // Push the tag filter into the query so only statements carrying a matching tag are
        // loaded, instead of loading every statement of the procedure and discarding the rest
        // in PHP.
        $tagsFilter = $this->requestStack->getCurrentRequest()->query->all('tagsFilter');
        $tagConditions = $this->statementExportTagFilter->buildStatementTagConditions($tagsFilter, $statementResourceType, $procedureId);

        $statementResult = $requestHandler->getObjectsByQueryParams(
            $this->requestStack->getCurrentRequest()->query,
            $statementResourceType,
            $tagConditions
        );
        /** @var Statement[] $statements */
        $statements = array_values($statementResult->getList());

        // Trim each loaded statement to only its matching segments. Runs on the already-narrowed set.
        $statements = $this->statementExportTagFilter->filterStatementsByTags($statements, $tagsFilter);

        $statementsWithCensoring = [];
        foreach ($statements as $statement) {
            $statementsWithCensoring[] = [
                $statement,
                $exporter->needsToBeCensored($statement, $censorCitizenData, $censorInstitutionData),
            ];
        }
        $statements = $zipPathResolver->resolve($statementsWithCensoring, $fileNameTemplate);

        return $zipExportService->buildZipStreamResponse(
            $fileNameGenerator->getSynopseFileName($procedure, 'zip'),
            function (ZipStream $zipStream) use (
                $statements,
                $exporter,
                $zipExportService,
                $procedure,
                $tableHeaders,
                $censorCitizenData,
                $censorInstitutionData,
                $obscureParameter,
                $customHeaderText,
            ): void {
                array_map(
                    function (Statement $statement, string $filePathInZip) use (
                        $exporter,
                        $zipExportService,
                        $zipStream,
                        $procedure,
                        $tableHeaders,
                        $censorCitizenData,
                        $censorInstitutionData,
                        $obscureParameter,
                        $customHeaderText,
                    ): void {
                        $docx = $exporter->exportStatementSegmentsInSeparateDocx(
                            $statement,
                            $procedure,
                            $tableHeaders,
                            $censorCitizenData,
                            $censorInstitutionData,
                            $obscureParameter,
                            $customHeaderText,
                        );
                        $writer = IOFactory::createWriter($docx);
                        $zipExportService->addWriterToZipStream(
                            $writer,
                            $filePathInZip,
                            $zipStream,
                            'statement_segments_zip_export',
                            self::DOCX_EXTENSION
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

    private function getBooleanQueryParameter(string $parameterName, bool $defaultValue = false): bool
    {
        $parameter = $this->requestStack->getCurrentRequest()->query->get($parameterName, $defaultValue);

        return filter_var($parameter, FILTER_VALIDATE_BOOLEAN);
    }
}
