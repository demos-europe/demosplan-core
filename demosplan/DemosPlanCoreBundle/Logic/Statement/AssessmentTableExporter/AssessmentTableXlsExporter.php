<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\FormOptionsResolver;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\SimpleSpreadsheetService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use League\HTMLToMarkdown\HtmlConverter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AssessmentTableXlsExporter extends AssessmentTableFileExporterAbstract
{
    /** @var Environment */
    protected $twig;
    /** @var ServiceImporter */
    protected $serviceImport;
    /** @var SimpleSpreadsheetService */
    protected $simpleSpreadsheetService;

    protected array $supportedTypes = ['xls', 'xlsx'];

    public function __construct(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentProcedureService $currentProcedureService,
        private readonly EditorService $editorService,
        Environment $twig,
        private readonly FormOptionsResolver $formOptionsResolver,
        LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        RequestStack $requestStack,
        ServiceImporter $serviceImport,
        SimpleSpreadsheetService $simpleSpreadsheetService,
        StatementHandler $statementHandler,
        TranslatorInterface $translator,
    ) {
        parent::__construct(
            $assessmentTableServiceOutput,
            $currentProcedureService,
            $assessmentHandler,
            $translator,
            $logger,
            $requestStack,
            $statementHandler
        );
        $this->serviceImport = $serviceImport;
        $this->simpleSpreadsheetService = $simpleSpreadsheetService;
        $this->twig = $twig;
    }

    public function supports(string $format): bool
    {
        return in_array($format, $this->supportedTypes, true);
    }

    /**
     * @throws HandlerException
     * @throws MessageBagException
     */
    public function __invoke(array $parameters): array
    {
        $procedureId = $parameters['procedureId'];
        $original = $parameters['original'];
        $outputResult = $this->assessmentHandler->prepareOutputResult(
            $procedureId,
            $original,
            $parameters
        );

        $statements = $outputResult->getStatements();
        $statementIds = array_column($statements, 'id');
        $columnsDefinition = $this->selectFormat($parameters['exportType']);

        try {
            $objWriter = $this->createExcel($statements, $columnsDefinition, $parameters['anonymous']);
        } catch (\Exception $e) {
            $this->logger->warning($e);
            throw HandlerException::assessmentExportFailedException('xlsx');
        }

        return [
            'filename' => sprintf(
                $this->translator->trans('considerationtable').'-%s.xlsx',
                Carbon::now('Europe/Berlin')->format('d-m-Y-H:i')
            ),
            'writer'       => $objWriter,
            'statementIds' => $statementIds,
        ];
    }

    /**
     * Creates a excel/xlsx document.
     *
     * @param array $columnDefinitions - (format, something like) =
     *                                 [
     *                                 [
     *                                 'key' => 'externId',
     *                                 'title' => $this->translator->trans('statement.id'),
     *                                 'width' => 20
     *                                 ],
     *                                 [
     *                                 'key' => 'recommendation',
     *                                 'title' => $this->translator->trans('recommendation.of.Statement'),
     *                                 'width' => 200
     *                                 ]
     *                                 ];
     * @param bool  $anonymous         - determines if text parts will be obscured
     *
     * @throws HandlerException
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function createExcel(array $statements, array $columnDefinitions = [[]], bool $anonymous = true): IWriter
    {
        // up until Excel 2016, this is the maximum number of columns in a sheet
        // see https://support.office.com/en-us/article/Excel-specifications-and-limits-1672b34d-7043-467e-8e27-269d656771c3#ID0EBABAAA=Excel_2007
        $maxExcelColumns = 16384;
        if ($maxExcelColumns < count($columnDefinitions)) {
            throw HandlerException::tooManyColumnDefinitionsException();
        }

        $attributesToExport = [];
        $columnTitles = [];
        $title = $this->translator->trans('considerationtable');
        $excelDocument = $this->simpleSpreadsheetService->createExcelDocument($title);

        // extract titles and keys
        foreach ($columnDefinitions as $columnDefinition) {
            $columnTitles[] = $columnDefinition['title'];
            $attributesToExport[] = $columnDefinition['key'];
        }

        // prepare Data for export:
        $formattedData = $this->prepareDataForExcelExport($statements, $anonymous, $attributesToExport);

        // add Worksheet with prepared data
        $filledExcelDocument =
            $this->simpleSpreadsheetService->addWorksheet($excelDocument, $formattedData, $columnTitles, $title);

        // set specific column width:
        $worksheet = $filledExcelDocument->getWorksheetIterator()->current();
        $dimensions = $worksheet->getColumnDimensions();

        foreach ($columnDefinitions as $index => $columnDefinition) {
            // add 1 to the index because column indexes are based on 1. So column A is the index 1
            $columnName = Coordinate::stringFromColumnIndex($index + 1);
            $dimensions[$columnName]->setWidth($columnDefinition['width']);
        }

        return $this->simpleSpreadsheetService->getExcel2007Writer($filledExcelDocument);
    }

    /**
     * Depending on the given format identifier, the corresponding column definitions will be returned.
     * The retuning definition includes the following informations:
     * - order of columns
     * - number of columns
     * - 'key' to access value of ES result,
     * - 'title' header of column,
     * - 'width' fixed width of column.
     */
    public function selectFormat(string $formatIdentifier): array
    {
        return match ($formatIdentifier) {
            'topicsAndTags'             => $this->createColumnsDefinitionForTopicsAndTags(),
            'potentialAreas'            => $this->createColumnsDefinitionForPotentialAreas(),
            'statementsWithAttachments' => $this->createColumnsDefinitionForStatementAttachments(), // WithAttachments
            'statements'                => $this->createColumnsDefinitionForStatementsOrSegments(true),
            'segments'                  => $this->createColumnsDefinitionForStatementsOrSegments(false),
            default                     => $this->createColumnsDefinitionDefault(),
        };
    }

    /**
     * Creates an array with default column definitions.
     */
    protected function createColumnsDefinitionDefault(): array
    {
        return [
            $this->createColumnDefinition('externId', 'statement.id'),
            $this->createColumnDefinition('name', 'statement.name'),
            $this->createColumnDefinition('recommendation', 'recommendation.of.Statement', 200),
        ];
    }

    /**
     * Creates an array with column definitions for topics and tags.
     */
    private function createColumnsDefinitionForTopicsAndTags(): array
    {
        return [
            $this->createColumnDefinition('externId', 'id'),
            $this->createColumnDefinition('uName', 'name'),
            $this->createColumnDefinition('topicNames', 'topic', 30),
            $this->createColumnDefinition('tagNames', 'tag', 40),
            $this->createColumnDefinition('recommendation', 'recommendation', 200),
        ];
    }

    /**
     * Creates an array with column definitions for potential areas.
     */
    private function createColumnsDefinitionForPotentialAreas(): array
    {
        return [
            $this->createColumnDefinition('externId', 'id'),
            $this->createColumnDefinition('uName', 'name'),
            $this->createColumnDefinition('priorityAreaKeys', 'potential.area'),
            $this->createColumnDefinition('recommendation', 'recommendation', 200),
        ];
    }

    /**
     * Creates an array with column definitions for statements.
     * Order of calls affects the order in the resulting xlsx document.
     */
    protected function createColumnsDefinitionForStatementsOrSegments(bool $isStatement): array
    {
        $columnsDefinition = [];

        $this->addColumnDefinition($columnsDefinition, 'externId', 'field_statement_extern_id', 'id');

        if ($isStatement) {
            $columnsDefinition[] = $this->createColumnDefinition('name', 'cluster.name');
        }

        $this->addColumnDefinition($columnsDefinition, 'text', 'field_statement_text', 'text');
        $this->addColumnDefinition(
            $columnsDefinition,
            'recommendation',
            'field_statement_recommendation',
            'recommendation'
        );
        $this->addColumnDefinition($columnsDefinition, 'countyNames', 'field_statement_county', 'county');

        $columnsDefinition[] = $this->createColumnDefinition('tagNames', 'tag');
        $columnsDefinition[] = $this->createColumnDefinition('topicNames', 'tag.category');

        if ($isStatement) {
            $columnsDefinition[] = $this->createColumnDefinition('elementTitle', 'document.category');
            $columnsDefinition[] = $this->createColumnDefinition('documentTitle', 'document');
            $columnsDefinition[] = $this->createColumnDefinition('paragraphTitle', 'paragraph.title');
        }

        $this->addColumnDefinition($columnsDefinition, 'status', 'field_statement_status', 'status');
        if ($isStatement) {
            $this->addColumnDefinition($columnsDefinition, 'priority', 'field_statement_priority', 'priority');
            $this->addColumnDefinition(
                $columnsDefinition,
                'votePla',
                'field_statement_vote_pla',
                'fragment.vote.short'
            );
        }

        $this->addColumnDefinition($columnsDefinition, 'oName', 'field_statement_meta_orga_name', 'organisation');
        $this->addColumnDefinition(
            $columnsDefinition,
            'dName',
            'field_statement_meta_orga_department_name',
            'department'
        );

        $columnsDefinition[] = $this->createColumnDefinition('meta.authorName', 'author');

        $this->addColumnDefinition(
            $columnsDefinition,
            'meta.submitName',
            'field_statement_meta_submit_name',
            'submitter'
        );
        $this->addColumnDefinition($columnsDefinition, 'meta.orgaEmail', 'field_statement_meta_email', 'email');
        $this->addColumnDefinition($columnsDefinition, 'meta.orgaStreet', 'field_statement_meta_street', 'street');
        $this->addColumnDefinition(
            $columnsDefinition,
            'meta.houseNumber',
            'feature_statement_meta_house_number_export',
            'street.number'
        );
        $this->addColumnDefinition(
            $columnsDefinition,
            'meta.orgaPostalCode',
            'field_statement_meta_postal_code',
            'postalcode'
        );
        $this->addColumnDefinition($columnsDefinition, 'meta.orgaCity', 'field_statement_meta_city', 'city');
        $this->addColumnDefinition($columnsDefinition, 'fileNames', 'field_statement_file', 'file.names');

        $columnsDefinition[] = $this->createColumnDefinition('submitDateString', 'statement.date.submitted');
        $columnsDefinition[] = $this->createColumnDefinition('meta.authoredDate', 'statement.date.authored');

        $this->addColumnDefinition($columnsDefinition, 'internId', 'field_statement_intern_id', 'internId');
        $this->addColumnDefinition($columnsDefinition, 'memo', 'field_statement_memo', 'memo');
        $this->addColumnDefinition($columnsDefinition, 'feedback', 'field_statement_feedback', 'feedback');
        $this->addColumnDefinition($columnsDefinition, 'votesNum', 'feature_statements_vote', 'voters');
        $this->addColumnDefinition($columnsDefinition, 'phase', 'field_statement_phase', 'procedure.public.phase');
        $this->addColumnDefinition($columnsDefinition, 'submitType', 'field_statement_submit_type', 'submit.type');
        $this->addColumnDefinition(
            $columnsDefinition,
            'sentAssessment',
            'field_send_final_email',
            'statement.final.sent',
            30
        );

        return $columnsDefinition;
    }

    /**
     * Creates an array with column definitions for statements
     * and adds a column for attachments.
     */
    protected function createColumnsDefinitionForStatementAttachments(): array
    {
        $columnsDefinition = $this->createColumnsDefinitionForStatementsOrSegments(true);
        $columnsDefinition[] =
            $this->createColumnDefinition('statementAttachments', 'statement.attachments.reference');
        $columnsDefinition[] =
            $this->createColumnDefinition('statementOriginalAttachment', 'statement.original.attachment.reference');

        return $columnsDefinition;
    }

    /**
     * Creates a definition for a column.
     */
    protected function createColumnDefinition(string $key, string $title, int $width = 20): array
    {
        return [
            'key'    => $key,
            'title'  => $this->translator->trans($title),
            'width'  => $width,
        ];
    }

    /**
     * Adds a definition for a column depending on the given permission.
     *
     * @param array       $columnsDefinition array of resulting column-definitions
     * @param string      $key               key to get value from statement array (elasticsearch result) later on
     * @param string|null $permission        permission to determine if column will be added
     * @param string|null $columnTitle       translation-key used as title for column in resulting document
     * @param int         $width             width of column in resulting document
     */
    private function addColumnDefinition(
        array &$columnsDefinition,
        string $key,
        string $permission,
        string $columnTitle,
        int $width = 20,
    ): void {
        if ($this->permissions->hasPermission($permission)) {
            $columnsDefinition[] = $this->createColumnDefinition($key, $columnTitle, $width);
        }
    }

    /**
     * Bring given statements into valid format to create phpExcel.
     *
     * @internal param $exportType
     */
    protected function prepareDataForExcelExport(
        array $statements,
        bool $anonymous,
        array $keysOfAttributesToExport,
    ): array {
        $attributeKeysWhichCauseNewLine = collect(['priorityAreaKeys', 'tagNames']);
        $formattedStatements = collect([]);

        // has permission to READ obscure text? else obscure text
        $anonymous = $this->permissions->hasPermission('feature_obscure_text') ? $anonymous : true;

        // collect Statements in unified data format
        foreach ($statements as $statement) {
            $pushed = false;
            $formattedStatement = $this->formatStatement($keysOfAttributesToExport, $statement);

            // loop again through the attributes
            foreach ($keysOfAttributesToExport as $attributeKey) {
                $isUsingDotNotation = str_contains((string) $attributeKey, '.');
                $isSortable = false;
                if (!$isUsingDotNotation) {
                    if (!array_key_exists($attributeKey, $statement)) {
                        continue;
                    }
                    $isNotEmptyArray = is_array($statement[$attributeKey]) && 0 < count($statement[$attributeKey]);
                    $isCausingNewLine = $attributeKeysWhichCauseNewLine->contains($attributeKey);
                    $isSortable = $isNotEmptyArray && $isCausingNewLine;
                }
                // make it sortable in exported excel table:
                // is current attribute value an array and should it be sortable and therefore be split in many single rows
                if ($isSortable) {
                    // new table row foreach attributevalue:
                    foreach ($statement[$attributeKey] as $singleAttributeValue) {
                        $formattedStatement[$attributeKey] = $singleAttributeValue;

                        // get Related TopicName of current single Tag to show only related topic to current tag in current row
                        if ('tagNames' === $attributeKey) {
                            // set value as key
                            foreach ($statement['tags'] as $tag) {
                                $statement['tags'][$tag['title']] = $tag;
                                if ($singleAttributeValue === $tag['title']) {
                                    // set only the topic name related to the current tag:
                                    $formattedStatement['topicNames'] = $tag['topicTitle'] ?? '';
                                }
                            }
                        }

                        // new formattedStatement to force new line in table
                        $formattedStatements->push($formattedStatement);
                        $pushed = true;
                    }
                }

                $formattedStatement[$attributeKey] =
                    $this->editorService->handleObscureTags((string) $formattedStatement[$attributeKey], $anonymous);
            }

            if (!$pushed) {
                $formattedStatements->push($formattedStatement);
            }
        }

        return $formattedStatements->toArray();
    }

    protected function formatStatement(array $keysOfAttributesToExport, array $statementArray): array
    {
        $formattedStatement = [];
        $htmlConverter = new HtmlConverter(['strip_tags' => true]);

        foreach ($keysOfAttributesToExport as $attributeKey) {
            $formattedStatement[$attributeKey] = $statementArray[$attributeKey] ?? null;

            // allow dot notation in export definition
            $explodedParts = explode('.', (string) $attributeKey);
            switch (count($explodedParts)) {
                case 2:
                    $formattedStatement[$attributeKey] = $statementArray[$explodedParts[0]][$explodedParts[1]];
                    break;
                case 3:
                    $formattedStatement[$attributeKey] =
                        $statementArray[$explodedParts[0]][$explodedParts[1]][$explodedParts[2]];
                    break;
                default:
                    break;
            }

            // simplify every attribute which is an array (to stirng)
            if (is_array($formattedStatement[$attributeKey])) {
                $formattedStatement[$attributeKey] = implode("\n", $formattedStatement[$attributeKey]);
            }

            if (in_array($attributeKey, ['text', 'recommendation'])) {
                $formattedStatement[$attributeKey] = $htmlConverter->convert($formattedStatement[$attributeKey]);
                $formattedStatement[$attributeKey] =
                    str_replace('\_', '_', $formattedStatement[$attributeKey]);
            }

            if ('status' === $attributeKey) {
                $formattedStatement[$attributeKey] = $this->formOptionsResolver->resolve(
                    FormOptionsResolver::STATEMENT_STATUS,
                    $formattedStatement[$attributeKey]
                );
            }

            if ('votePla' === $attributeKey) {
                $formattedStatement[$attributeKey] = $this->formOptionsResolver->resolve(
                    FormOptionsResolver::STATEMENT_FRAGMENT_ADVICE_VALUES,
                    $formattedStatement[$attributeKey] ?? ''
                );
            }

            if (true === $formattedStatement[$attributeKey]) {
                $formattedStatement[$attributeKey] = 'x';
            }
        }

        $formattedStatement['externId'] = $this->assessmentTableOutput->createExternIdString($statementArray);

        // in xlsx export, the information about moved Statement, have to be in the field of the externID
        if (isset($statementArray['movedToProcedureName'])) {
            $formattedStatement['externId'] .= ' '.$this->translator->trans(
                'statement.moved',
                ['name' => $statementArray['movedToProcedureName']]
            );
        }

        return $formattedStatement;
    }
}
