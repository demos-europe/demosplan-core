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
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\ORM\Query\QueryException;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use ZipStream\ZipStream;

class SegmentsExportController extends BaseController
{
    private const OUTPUT_DESTINATION = 'php://output';
    private const TABLE_HEADERS_PARAMETER = 'tableHeaders';
    private const FILE_NAME_TEMPLATE_PARAMETER = 'fileNameTemplate';
    private const CITIZEN_CENSOR_PARAMETER = 'isCitizenDataCensored';
    private const INSTITUTION_CENSOR_PARAMETER = 'isInstitutionDataCensored';
    private const OBSCURE_PARAMETER = 'isObscured';

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
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        string $procedureId,
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->all(self::TABLE_HEADERS_PARAMETER);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $requestHandler->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $statementResourceType
            )->getList()
        );

        $censorCitizenData = $this->getBooleanQueryParameter(self::CITIZEN_CENSOR_PARAMETER);
        $censorInstitutionData = $this->getBooleanQueryParameter(self::INSTITUTION_CENSOR_PARAMETER);
        // geschwärzt
        $obscureParameter = $this->getBooleanQueryParameter(self::OBSCURE_PARAMETER);

        $response = new StreamedResponse(
            static function () use (
                $tableHeaders,
                $procedure,
                $statementEntities,
                $exporter,
                $censorCitizenData,
                $censorInstitutionData,
                $obscureParameter
            ) {
                $exportedDoc = $exporter->exportAll(
                    $tableHeaders,
                    $procedure,
                    $obscureParameter,
                    $censorCitizenData,
                    $censorInstitutionData,
                    ...$statementEntities
                );
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
        SegmentsByStatementsExporter $exporter,
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
        SegmentsByStatementsExporter $exporter,
        StatementResourceType $statementResourceType,
        JsonApiActionService $requestHandler,
        ZipExportService $zipExportService,
        string $procedureId,
    ): StreamedResponse {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $this->requestStack->getCurrentRequest()->query->all(self::TABLE_HEADERS_PARAMETER);
        $fileNameTemplate = $this->requestStack->getCurrentRequest()->query->get(self::FILE_NAME_TEMPLATE_PARAMETER, '');

        $censorInstitutionData = $this->getBooleanQueryParameter(self::INSTITUTION_CENSOR_PARAMETER);
        $censorCitizenData = $this->getBooleanQueryParameter(self::CITIZEN_CENSOR_PARAMETER);
        $obscureParameter = $this->getBooleanQueryParameter(self::OBSCURE_PARAMETER);

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
        $statements = $exporter->mapStatementsToPathInZip(
            $statements,
            $censorCitizenData,
            $censorInstitutionData,
            $fileNameTemplate
        );

        return $zipExportService->buildZipStreamResponse(
            $fileNameGenerator->getSynopseFileName($procedure, 'zip'),
            static function (ZipStream $zipStream) use (
                $statements,
                $exporter,
                $zipExportService,
                $procedure,
                $tableHeaders,
                $censorCitizenData,
                $censorInstitutionData,
                $obscureParameter
            ): void {
                array_map(
                    static function (Statement $statement, string $filePathInZip) use (
                        $exporter,
                        $zipExportService,
                        $zipStream,
                        $procedure,
                        $tableHeaders,
                        $censorCitizenData,
                        $censorInstitutionData,
                        $obscureParameter
                    ): void {
                        $docx = $exporter->exportStatementSegmentsInSeparateDocx(
                            $statement,
                            $procedure,
                            $tableHeaders,
                            $censorCitizenData,
                            $censorInstitutionData,
                            $obscureParameter
                        );
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
