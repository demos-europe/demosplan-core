<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Export\StatementZipPathResolver;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use EDT\JsonApi\RequestHandling\UrlParameter;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

/**
 * Builds the grouped-segments DOCX/ODT and ZIP export responses from the export query params, shared
 * by the synchronous controller actions and the asynchronous {@see ExportSegmentsMessageHandler}.
 */
class SegmentsExportResponseBuilder
{
    public const TYPE_DOCX = 'docx';
    public const TYPE_ZIP = 'zip';

    public const TABLE_HEADERS_PARAMETER = 'tableHeaders';
    public const FILE_NAME_TEMPLATE_PARAMETER = 'fileNameTemplate';
    public const CITIZEN_CENSOR_PARAMETER = 'isCitizenDataCensored';
    public const INSTITUTION_CENSOR_PARAMETER = 'isInstitutionDataCensored';
    public const OBSCURE_PARAMETER = 'isObscured';

    private const DOCX_EXTENSION = '.docx';

    public function __construct(
        private readonly FileNameGenerator $fileNameGenerator,
        private readonly JsonApiActionService $jsonApiActionService,
        private readonly NameGenerator $nameGenerator,
        private readonly SegmentsByStatementsExporter $exporter,
        private readonly StatementExportTagFilter $statementExportTagFilter,
        private readonly StatementResourceType $statementResourceType,
        private readonly StatementZipPathResolver $zipPathResolver,
        private readonly ZipExportService $zipExportService,
    ) {
    }

    /**
     * @param self::TYPE_* $exportType
     */
    public function build(Procedure $procedure, ParameterBag $query, string $exportType): StreamedResponse
    {
        return self::TYPE_ZIP === $exportType
            ? $this->buildZipResponse($procedure, $query)
            : $this->buildGroupedDocxResponse($procedure, $query);
    }

    private function buildGroupedDocxResponse(Procedure $procedure, ParameterBag $query): StreamedResponse
    {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $query->all(self::TABLE_HEADERS_PARAMETER);
        $censorCitizenData = $this->getBooleanParameter($query, self::CITIZEN_CENSOR_PARAMETER);
        $censorInstitutionData = $this->getBooleanParameter($query, self::INSTITUTION_CENSOR_PARAMETER);
        // geschwärzt
        $obscureParameter = $this->getBooleanParameter($query, self::OBSCURE_PARAMETER);
        $customHeaderText = $query->get('customHeaderText') ?? '';

        $statementEntities = $this->resolveStatements($procedure->getId(), $query);
        $exportFilteredByTagsWithTopics = $this->statementExportTagFilter->getFilteredTagsWithTitles();

        $response = new StreamedResponse(
            function () use (
                $tableHeaders,
                $procedure,
                $statementEntities,
                $censorCitizenData,
                $censorInstitutionData,
                $obscureParameter,
                $exportFilteredByTagsWithTopics,
                $customHeaderText
            ) {
                $exportedDoc = $this->exporter->exportAll(
                    $tableHeaders,
                    $procedure,
                    $obscureParameter,
                    $exportFilteredByTagsWithTopics,
                    $censorCitizenData,
                    $censorInstitutionData,
                    $customHeaderText,
                    ...$statementEntities
                );
                $exportedDoc->save('php://output');
            }
        );

        $this->setDocxResponseHeaders($response, $this->getSynopseFileName($procedure, $query, 'docx'));

        return $response;
    }

    private function buildZipResponse(Procedure $procedure, ParameterBag $query): StreamedResponse
    {
        /** @var array<string, string> $tableHeaders */
        $tableHeaders = $query->all(self::TABLE_HEADERS_PARAMETER);
        $fileNameTemplate = $query->get(self::FILE_NAME_TEMPLATE_PARAMETER, '');
        $censorCitizenData = $this->getBooleanParameter($query, self::CITIZEN_CENSOR_PARAMETER);
        $censorInstitutionData = $this->getBooleanParameter($query, self::INSTITUTION_CENSOR_PARAMETER);
        $obscureParameter = $this->getBooleanParameter($query, self::OBSCURE_PARAMETER);
        $customHeaderText = $query->get('customHeaderText') ?? '';

        $statements = $this->resolveStatements($procedure->getId(), $query);

        $statementsWithCensoring = [];
        foreach ($statements as $statement) {
            $statementsWithCensoring[] = [
                $statement,
                $this->exporter->needsToBeCensored($statement, $censorCitizenData, $censorInstitutionData),
            ];
        }
        $statements = $this->zipPathResolver->resolve($statementsWithCensoring, $fileNameTemplate);

        return $this->zipExportService->buildZipStreamResponse(
            $this->fileNameGenerator->getSynopseFileName($procedure, 'zip'),
            function (ZipStream $zipStream) use (
                $statements,
                $procedure,
                $tableHeaders,
                $censorCitizenData,
                $censorInstitutionData,
                $obscureParameter,
                $customHeaderText,
            ): void {
                array_map(
                    function (Statement $statement, string $filePathInZip) use (
                        $zipStream,
                        $procedure,
                        $tableHeaders,
                        $censorCitizenData,
                        $censorInstitutionData,
                        $obscureParameter,
                        $customHeaderText,
                    ): void {
                        $docx = $this->exporter->exportStatementSegmentsInSeparateDocx(
                            $statement,
                            $procedure,
                            $tableHeaders,
                            $censorCitizenData,
                            $censorInstitutionData,
                            $obscureParameter,
                            $customHeaderText,
                        );
                        $writer = IOFactory::createWriter($docx);
                        $this->zipExportService->addWriterToZipStream(
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

    /**
     * Loads the statements matching the JSON:API filter/search/sort and the tag filter of the query,
     * with the same access restrictions as the generic statement API.
     *
     * @return Statement[]
     */
    public function resolveStatements(string $procedureId, ParameterBag $query): array
    {
        $tagsFilter = $query->all('tagsFilter');
        // Push the tag filter into the query so only statements carrying a matching tag are
        // loaded, instead of loading every statement of the procedure and discarding the rest
        // in PHP.
        $tagConditions = $this->statementExportTagFilter->buildStatementTagConditions(
            $tagsFilter,
            $this->statementResourceType,
            $procedureId
        );

        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $this->jsonApiActionService->getObjectsByQueryParams(
                $query,
                $this->statementResourceType,
                $tagConditions
            )->getList()
        );

        // Trim each loaded statement to only its matching segments and collect the matched tag
        // titles for the export header. Runs on the already-narrowed statement set.
        return $this->statementExportTagFilter->filterStatementsByTags($statementEntities, $tagsFilter);
    }

    public function getSynopseFileName(Procedure $procedure, ParameterBag $query, string $extension): string
    {
        $isFiltered = 0 !== count($query->all('tagsFilter')) || 0 !== count($query->all(UrlParameter::FILTER));

        return $this->fileNameGenerator->getSynopseFileName($procedure, $extension, $isFiltered);
    }

    public function getBooleanParameter(ParameterBag $query, string $name): bool
    {
        return filter_var($query->get($name, false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setDocxResponseHeaders(StreamedResponse $response, string $filename): void
    {
        $response->headers->set('Pragma', 'public');
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8'
        );
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($filename));
    }
}
