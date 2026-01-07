<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterHandleSegmentsEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterPrePersistTagsEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Exceptions\AddonResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Constraint\DateStringConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\MatchingFieldValueInSegments;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Event\Statement\ExcelImporterHandleSegmentsEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\ExcelImporterPrePersistTagsEvent;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTitleException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingExcelDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\WorkflowPlaceNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\HtmlSanitizerService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use demosplan\DemosPlanCoreBundle\Validator\StatementValidator;
use demosplan\DemosPlanCoreBundle\ValueObject\Import\StatementProcessingContext;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\PathException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;
use Webmozart\Assert\Assert;

class ExcelImporter extends AbstractStatementSpreadsheetImporter
{
    private const SUBMIT_TYPE_EMAIL_TRANSLATED = 'E-Mail';
    private const SUBMIT_TYPE_LETTER_TRANSLATED = 'Brief';
    private const SUBMIT_TYPE_FAX_TRANSLATED = 'Fax';
    private const SUBMIT_TYPE_EAKTE_TRANSLATED = 'E-Akte';
    private const SUBMIT_TYPE_SYSTEM_TRANSLATED = 'Beteiligungsplattform';
    private const SUBMIT_TYPE_DECLARATION_TRANSLATED = 'Niederschrift';
    private const SUBMIT_TYPE_UNSPECIFIED_TRANSLATED = 'Sonstige';
    private const SUBMIT_TYPE_UNKNOWN_TRANSLATED_UC = 'Unbekannt';
    private const SUBMIT_TYPE_UNKNOWN_TRANSLATED_LC = 'unbekannt';
    private const SUBMIT_TYPE_COLUMN = 'Art der Einreichung';
    final public const STATEMENT_ID = 'Stellungnahme ID';
    private const PUBLIC_STATEMENT = 'publicStatement';
    private const STATEMENT_TEXT = 'Stellungnahmetext';

    private const SUBMIT_TYPE_MAPPING = [
        self::SUBMIT_TYPE_EMAIL_TRANSLATED       => Statement::SUBMIT_TYPE_EMAIL,
        self::SUBMIT_TYPE_LETTER_TRANSLATED      => Statement::SUBMIT_TYPE_LETTER,
        self::SUBMIT_TYPE_FAX_TRANSLATED         => Statement::SUBMIT_TYPE_FAX,
        self::SUBMIT_TYPE_EAKTE_TRANSLATED       => Statement::SUBMIT_TYPE_EAKTE,
        self::SUBMIT_TYPE_SYSTEM_TRANSLATED      => Statement::SUBMIT_TYPE_SYSTEM,
        self::SUBMIT_TYPE_DECLARATION_TRANSLATED => Statement::SUBMIT_TYPE_DECLARATION,
        self::SUBMIT_TYPE_UNSPECIFIED_TRANSLATED => Statement::SUBMIT_TYPE_UNSPECIFIED,
        ''                                       => Statement::SUBMIT_TYPE_UNKNOWN,
        self::SUBMIT_TYPE_UNKNOWN_TRANSLATED_UC  => Statement::SUBMIT_TYPE_UNKNOWN,
        self::SUBMIT_TYPE_UNKNOWN_TRANSLATED_LC  => Statement::SUBMIT_TYPE_UNKNOWN,
    ];

    final public const PUBLIC = 'Öffentlichkeit';
    final public const INSTITUTION = 'Institution';
    private const LEGENDE_WORKSHEET = 'Legende';
    private const STATEMENT_PROCEDURE_PERSON_WORKSHEET = 'weitere Einreichende';

    /**
     * @var Segment[]
     */
    private $generatedSegments = [];

    /**
     * Temporary mapping of Excel ID => Statement to handle 'weitere Einreichende' relations
     * before statements are persisted to database.
     *
     * @var array<string, Statement>
     */
    private array $excelIdToStatementMapping = [];

    /**
     * Cache for the first workflow place to avoid repeated database queries
     * during segment import (same place is used for all segments).
     *
     * @var array<string, Place|null> Keyed by procedure ID
     */
    private array $firstWorkflowPlaceCache = [];

    public function __construct(
        CurrentProcedureService $currentProcedureService,
        CurrentUserInterface $currentUser,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly EntityManagerInterface $entityManager,
        ElementsService $elementsService,
        OrgaService $orgaService,
        private readonly PlaceService $placeService,
        private readonly SegmentValidator $segmentValidator,
        StatementService $statementService,
        private readonly StatementValidator $statementValidator,
        private readonly TagResourceType $tagResourceType,
        private readonly TagService $tagService,
        private readonly TagValidator $tagValidator,
        TranslatorInterface $translator,
        ValidatorInterface $validator,
        StatementCopier $statementCopier,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly HtmlSanitizerService $htmlSanitizerService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            $currentProcedureService,
            $currentUser,
            $elementsService,
            $orgaService,
            $statementCopier,
            $statementService,
            $translator,
            $validator
        );
    }

    /**
     * Generates statements from incoming excel document, including validation.
     * This method does not flush the generated Statements and does not persist nor flush the original statements.
     *
     * @throws UnexpectedWorksheetNameException
     * @throws MissingDataException
     * @throws MissingExcelDataException
     * @throws CopyException
     * @throws InvalidDataException
     * @throws StatementElementNotFoundException
     * @throws UserNotFoundException
     * @throws InvalidArgumentException
     * @throws MissingPostParameterException
     * @throws MissingExcelDataException
     */
    public function process(SplFileInfo $workbook): void
    {
        $this->generatedStatements = [];
        $this->generatedTags = [];
        $this->errors = [];
        $this->excelIdToStatementMapping = [];
        $this->firstWorkflowPlaceCache = [];
        $worksheets = $this->extractWorksheets($workbook, 1);
        // get the worksheet in the correct order - sheets including statements have to be processed first
        $worksheets = $this->sortWorkSheets($worksheets);

        foreach ($worksheets as $worksheet) {
            /** @var string{'Legende'|'weitere Einreichende'|'Öffentlichkeit'|'Institution'} $currentWorksheetTitle */
            $currentWorksheetTitle = $worksheet->getTitle() ?? '';
            if (self::PUBLIC === $currentWorksheetTitle
                || self::INSTITUTION === $currentWorksheetTitle
            ) {
                $this->processStatementsWorksheet($worksheet);
            }
            // Process 'weitere Einreichende' worksheet after main statements are created
            if (self::STATEMENT_PROCEDURE_PERSON_WORKSHEET === $currentWorksheetTitle
                && $this->currentUser->hasPermission('feature_similar_statement_submitter')
            ) {
                $this->processWeitereEinreichende($worksheet);
            }
        }
    }

    /**
     * @throws CopyException
     * @throws DuplicatedTagTitleException
     * @throws InvalidDataException
     * @throws MissingPostParameterException
     * @throws PathException
     * @throws StatementElementNotFoundException
     * @throws UnexpectedWorksheetNameException
     * @throws UserNotFoundException
     * @throws AddonResourceNotFoundException
     */
    public function processSegments(SplFileInfo $fileInfo): SegmentExcelImportResult
    {
        $result = new SegmentExcelImportResult();

        // Clear ALL entity caches at start of each import job
        // Caches can contain detached entities from previous jobs if:
        // 1. Previous job failed and EntityManager.clear() was called
        // 2. EntityManager was cleared for any other reason
        // Without this, segments reference detached entities causing cascade persist errors
        $this->firstWorkflowPlaceCache = [];
        $this->generatedTags = [];
        $this->generatedStatements = [];
        $this->errors = [];
        $this->excelIdToStatementMapping = [];

        if (!$this->currentUser->hasPermission('feature_segment_recommendation_edit')) {
            throw new AccessDeniedException('Current user is not permitted to create or edit segments.');
        }

        $step = microtime(true);
        [$segmentsWorksheet, $metaDataWorksheet] = $this->getSegmentImportWorksheets($fileInfo);
        $this->logger->info('[ExcelImporter] Worksheets loaded', [
            'duration_sec' => round(microtime(true) - $step, 2),
            'memory_mb'    => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $step = microtime(true);
        $segmentWorksheetTitle = $this->getTitle($segmentsWorksheet);
        $segments = $this->getGroupedSegmentsFromWorksheet($segmentsWorksheet, $result);
        $this->logger->info('[ExcelImporter] Segments parsed from worksheet', [
            'segments_count' => count($segments),
            'duration_sec'   => round(microtime(true) - $step, 2),
            'memory_mb'      => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        unset($segmentsWorksheet);
        // PHP's automatic GC will handle memory cleanup - manual gc_collect_cycles() adds overhead

        // option for addons to process additional information of import file
        $step = microtime(true);
        $event = $this->dispatcher->dispatch(
            new ExcelImporterHandleSegmentsEvent($segments),
            ExcelImporterHandleSegmentsEventInterface::class
        );
        $this->logger->info('[ExcelImporter] Segment event dispatched', [
            'duration_sec' => round(microtime(true) - $step, 2),
        ]);

        $segments = $event->getSegments();

        $step = microtime(true);
        $miscTopic = $this->findOrCreateMiscTagTopic();
        $this->logger->info('[ExcelImporter] Misc topic found/created', [
            'duration_sec' => round(microtime(true) - $step, 2),
        ]);

        // Memory optimization: Get column names and actual highest data column
        [$columnNamesMeta, $highestDataColumnMeta] = $this->getFirstRowOfWorksheetWithHighestColumn($metaDataWorksheet);
        $statementWorksheetTitle = $metaDataWorksheet->getTitle() ?? '';

        $step = microtime(true);
        $processedStatements = 0;
        $processedSegments = 0;

        $context = new StatementProcessingContext(
            $metaDataWorksheet,
            $columnNamesMeta,
            $segmentWorksheetTitle,
            $statementWorksheetTitle,
            0,
            $step,
            $highestDataColumnMeta
        );

        foreach ($metaDataWorksheet->getRowIterator(2) as $statementLine => $row) {
            // Update context with current processing stats
            $context = new StatementProcessingContext(
                $context->worksheet,
                $context->columnNamesMeta,
                $context->segmentWorksheetTitle,
                $context->statementWorksheetTitle,
                $processedStatements,
                $step,
                $context->highestDataColumn
            );

            $segmentsCreated = $this->processStatementRow(
                $row,
                $context,
                $segments,
                $miscTopic,
                $statementLine,
                $result
            );

            if ($segmentsCreated > 0) {
                ++$processedStatements;
                $processedSegments += $segmentsCreated;
            }
        }

        $this->logger->info('[ExcelImporter] All statements and segments created', [
            'total_statements' => $processedStatements,
            'total_segments'   => $processedSegments,
            'duration_sec'     => round(microtime(true) - $step, 2),
            'memory_mb'        => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // option for addons to gather the persisted ids and core relations of imported segments
        $this->dispatcher->dispatch(
            new ExcelImporterPrePersistTagsEvent(segments: $result->getSegments()),
            ExcelImporterPrePersistTagsEventInterface::class
        );

        return $result;
    }

    protected function validateSubmitType(string $inputSubmitType, int $line, string $worksheetTitle): void
    {
        $violations = $this->validator->validate($inputSubmitType, $this->getSubmitTypeConstraint($inputSubmitType));
        if (0 !== $violations->count()) {
            $this->addImportViolations($violations, $line, $worksheetTitle);
        }
    }

    /**
     * It is important to process the worksheets self::PUBLIC and self::INSTITUTION prior to the
     * self::STATEMENT_PROCEDURE_PERSON_WORKSHEET for included references to work
     * self::STATEMENT_PROCEDURE_PERSON_WORKSHEET depends on $this->excelIdToStatementMapping being setup beforehand.
     *
     * @param array<Worksheet> $worksheets
     *
     * @return array<Worksheet>
     *
     * @throws UnexpectedWorksheetNameException
     * @throws MissingDataException
     */
    private function sortWorkSheets(array $worksheets): array
    {
        $sortedWorksheets = [];
        $indexMap = [
            self::PUBLIC                               => null,
            self::INSTITUTION                          => null,
            self::LEGENDE_WORKSHEET                    => null,
            self::STATEMENT_PROCEDURE_PERSON_WORKSHEET => null,
        ];
        foreach ($worksheets as $worksheet) {
            $worksheetTitle = $worksheet->getTitle() ?? '';
            if (!array_key_exists($worksheetTitle, $indexMap)) {
                throw new UnexpectedWorksheetNameException($worksheetTitle, $indexMap);
            }
            $indexMap[$worksheetTitle] = $worksheet;
        }
        if (null === $indexMap[self::PUBLIC] && null === $indexMap[self::INSTITUTION]) {
            throw new MissingDataException('The Excel Statement import is missing mandatory worksheets');
        }
        if (null !== $indexMap[self::PUBLIC]) {
            $sortedWorksheets[] = $indexMap[self::PUBLIC];
        }
        if (null !== $indexMap[self::INSTITUTION]) {
            $sortedWorksheets[] = $indexMap[self::INSTITUTION];
        }
        if (null !== $indexMap[self::STATEMENT_PROCEDURE_PERSON_WORKSHEET]) {
            $sortedWorksheets[] = $indexMap[self::STATEMENT_PROCEDURE_PERSON_WORKSHEET];
        }
        if (null !== $indexMap[self::LEGENDE_WORKSHEET]) {
            $sortedWorksheets[] = $indexMap[self::LEGENDE_WORKSHEET];
        }

        return $sortedWorksheets;
    }

    /**
     * @throws CopyException
     * @throws InvalidDataException
     * @throws MissingPostParameterException
     * @throws StatementElementNotFoundException
     * @throws UnexpectedWorksheetNameException
     * @throws UserNotFoundException
     */
    private function processStatementsWorksheet(Worksheet $publicOrInstitutionWorksheet): void
    {
        $currentWorksheetTitle = $publicOrInstitutionWorksheet->getTitle();
        Assert::oneOf($currentWorksheetTitle, [self::PUBLIC, self::INSTITUTION]);
        $publicStatement = $this->getPublicStatement($currentWorksheetTitle);
        $statementData = $publicOrInstitutionWorksheet->toArray();
        $columnNames = array_shift($statementData);
        $columnNames = $this->trimCellEntries($columnNames);
        if (0 === count($statementData)) {
            throw new MissingDataException('No data in rows found.');
        }
        foreach ($statementData as $line => $statement) {
            if ($this->isEmpty($statement)) {
                continue;
            }
            $statement = $this->trimCellEntries($statement);
            $statement = \array_combine($columnNames, $statement);
            $statement[self::PUBLIC_STATEMENT] = $publicStatement;

            $generatedOriginalStatement = $this->createNewOriginalStatement(
                $statement,
                count($this->generatedStatements),
                $line,
                $currentWorksheetTitle
            );
            // no validation of $generatedOriginalStatement?

            // Skip flush - let batch processing in XlsxSegmentImport handle it
            $generatedStatement = $this->createCopy($generatedOriginalStatement, flush: false);

            $constraints = $this->statementValidator->validate(
                $generatedStatement,
                [StatementInterface::IMPORT_VALIDATION]
            );
            if (0 === $constraints->count()) {
                $this->generatedStatements[] = $generatedStatement;
                // Store statement in mapping for potential existing worksheet 'weitere Einreichende'
                // processed later on to append multiple Persons to statement-references
                $excelId = $statement['ID'] ?? null;
                if ($excelId) {
                    $this->excelIdToStatementMapping[$excelId] = $generatedStatement;
                }
            } else {
                $this->addImportViolations($constraints, $line, $currentWorksheetTitle);
            }
        }
    }

    private function trimCellEntries(array $cellEntries): array
    {
        return array_map(
            function ($columnName) {
                if (is_string($columnName)) {
                    return trim($columnName);
                }

                return $columnName;
            },
            $cellEntries
        );
    }

    /**
     * Iterates through the given {@link Worksheet} and transfers each row into an array containing data for one segment.
     *
     * Does not create entities or accesses the database in any way.
     *
     * @return array<string, array<int, array<string, mixed>>> an array of segments grouped by their statement IDs
     */
    private function getGroupedSegmentsFromWorksheet(Worksheet $segmentsWorksheet, SegmentExcelImportResult $result): array
    {
        // Memory optimization: Get column names and actual highest data column
        [$columnNamesSegments, $highestDataColumn] = $this->getFirstRowOfWorksheetWithHighestColumn($segmentsWorksheet);
        $segmentsWorksheetTitle = $this->getTitle($segmentsWorksheet);

        // Debug: Log column names to identify why tags aren't being imported
        $this->logger->info('[ExcelImporter] Segments worksheet columns', [
            'columns'         => $columnNamesSegments,
            'has_schlagworte' => in_array('Schlagworte', $columnNamesSegments, true),
            'highest_column'  => $highestDataColumn,
        ]);

        $segments = [];
        foreach ($segmentsWorksheet->getRowIterator(2) as $segmentLine => $row) {
            $segmentIterator = $row->getCellIterator('A', $highestDataColumn);
            $segmentData = array_map(fn (Cell $cell) => $this->replaceLineBreak($cell->getValue()), \iterator_to_array($segmentIterator));

            if ($this->isEmpty($segmentData)) {
                continue;
            }

            $segmentData = \array_combine($columnNamesSegments, $segmentData);
            if (!\is_array($segmentData)) {
                continue;
            }
            $segmentData['segment_line'] = $segmentLine;

            $idConstraints = $this->validator->validate($segmentData[self::STATEMENT_ID], $this->notNullConstraint);
            if (0 !== $idConstraints->count()) {
                $result->addErrors($idConstraints, $segmentLine, $segmentsWorksheetTitle);

                continue;
            }

            $statementId = $segmentData[self::STATEMENT_ID];
            if (!\array_key_exists($statementId, $segments)) {
                $segments[$statementId] = [];
            }

            $segments[$statementId][] = $segmentData;
        }

        // Debug: Log segment distribution per statement
        $segmentDistribution = [];
        foreach ($segments as $stmtId => $stmtSegments) {
            $segmentDistribution[$stmtId] = count($stmtSegments);
        }
        $this->logger->info('[ExcelImporter] Segment distribution by statement ID', [
            'total_statements' => count($segments),
            'distribution'     => $segmentDistribution,
        ]);

        return $segments;
    }

    /**
     * @return array{0: Worksheet, 1: Worksheet}
     */
    private function getSegmentImportWorksheets(SplFileInfo $fileInfo): array
    {
        $worksheets = $this->extractWorksheets($fileInfo, 2);

        [$segmentsWorksheet, $metaDataWorksheet] = $worksheets;

        if (0 === $segmentsWorksheet->getHighestRow()) {
            throw new MissingDataException('No segment data in rows found.');
        }

        if (0 === $metaDataWorksheet->getHighestRow()) {
            throw new MissingDataException('No meta data in rows found.');
        }

        return $worksheets;
    }

    private function getTitle(Worksheet $worksheet): string
    {
        return $worksheet->getTitle() ?? '';
    }

    /**
     * @throws UnexpectedWorksheetNameException
     */
    private function getPublicStatement(string $statementType): string
    {
        return match ($statementType) {
            self::INSTITUTION => Statement::INTERNAL,
            self::PUBLIC      => Statement::EXTERNAL,
            default           => throw new UnexpectedWorksheetNameException($statementType, [self::PUBLIC, self::INSTITUTION]),
        };
    }

    /**
     * Checks if the given $input (array) only contains null values and empty strings.
     *
     * @param array<int, mixed> $input
     */
    public function isEmpty(array $input): bool
    {
        return [] === array_filter(
            $input,
            static fn ($field) => null !== $field && (!is_string($field) || '' !== trim($field))
        );
    }

    /**
     * @throws DuplicatedTagTitleException
     * @throws PathException
     * @throws AddonResourceNotFoundException
     */
    public function generateSegment(
        Statement $statement,
        array $segmentData,
        int $counter,
        int $line,
        string $worksheetTitle,
        TagTopic $miscTopic,
    ): Segment {
        if (!$this->currentUser->hasPermission('feature_segment_recommendation_edit')) {
            throw new AccessDeniedException('Current user is not permitted to create or edit segments.');
        }

        $procedure = $statement->getProcedure();

        $segment = new Segment();
        $segment->setParentStatementOfSegment($statement);
        $segment->setProcedure($procedure);
        $segment->setExternId($statement->getExternId().'-'.$counter);
        $segment->setPhase('participation');
        $segment->setPublicVerified(Statement::PUBLICATION_PENDING);
        $segment->setText($segmentData['Einwand'] ?? '');
        $segment->setRecommendation($segmentData['Erwiderung'] ?? '');

        // Use cached workflow place to avoid repeated database queries
        $procedureId = $procedure->getId();
        if (!isset($this->firstWorkflowPlaceCache[$procedureId])) {
            $this->firstWorkflowPlaceCache[$procedureId] = $this->placeService->findFirstOrderedBySortIndex($procedureId);
        }

        $place = $this->firstWorkflowPlaceCache[$procedureId];
        if (!$place instanceof Place) {
            throw WorkflowPlaceNotFoundException::createResourceNotFoundException('Place', $procedureId);
        }

        $segment->setPlace($place);
        $segment->setCreated(new DateTime());
        $segment->setOrderInProcedure($counter);

        // Handle Tags
        if (array_key_exists('Schlagworte', $segmentData) && '' !== $segmentData['Schlagworte'] && null !== $segmentData['Schlagworte']) {
            $procedureId = $statement->getProcedure()->getId();
            $tagTitlesString = $segmentData['Schlagworte'];
            if (is_numeric($tagTitlesString)) {
                $tagTitlesString = (string) $tagTitlesString;
            }
            $tagTitles = explode(',', (string) $tagTitlesString);

            foreach ($tagTitles as $tagTitle) {
                $tagTitle = new UnicodeString($tagTitle);
                $tagTitle = $tagTitle->trim()->toString();
                $matchingTag = $this->getMatchingTag($tagTitle, $procedureId);

                $createNewTag = !$matchingTag instanceof Tag;
                if ($createNewTag) {
                    $matchingTag = $this->tagService->createTag($tagTitle, $miscTopic, false);
                }

                // Check if valid tag
                $violations = $this->tagValidator->validate($matchingTag, ['segments_import']);

                if (0 === $violations->count()) {
                    $segment->addTag($matchingTag);
                    if ($createNewTag) {
                        $this->generatedTags[] = $matchingTag;
                    }
                } else {
                    $this->addImportViolations($violations, $line, $worksheetTitle);
                }
            }
        }

        return $segment;
    }

    /**
     * This method only support creation of a statement without extra relations like files, paragraphs, documents,
     * ...
     *
     * @param array<string, mixed> $statementData array which is holding the data of Statement to create
     * @param int                  $offset        will be used to calculate next valid externId in the procedure for the current statement to ensure it being unique,
     *                                            because StatementRepository::getNextValidExternalIdForProcedure()
     *                                            only takes already persisted Statements and DraftStatements into account, it
     *                                            is necessary to add the number of already generated but not persisted
     *                                            to ensure getting a unique ID
     *
     * @throws InvalidDataException
     * @throws MissingPostParameterException
     * @throws StatementElementNotFoundException
     * @throws UserNotFoundException
     *
     * @see StatementRepository::getNextValidExternalIdForProcedure()
     */
    public function createNewOriginalStatement(array $statementData, int $offset, int $line, string $currentWorksheetTitle): Statement
    {
        $newOriginalStatement = new Statement();
        $newStatementMeta = new StatementMeta();
        $currentProcedure = $this->currentProcedureService->getProcedure();

        if (!$currentProcedure instanceof Procedure) {
            throw new MissingPostParameterException('Current procedure is missing.');
        }

        $newOriginalStatement->setPublicStatement($statementData[self::PUBLIC_STATEMENT]);
        if (Statement::EXTERNAL === $newOriginalStatement->getPublicStatement()) {
            $newOriginalStatement->setOrganisation($this->orgaService->getOrga(User::ANONYMOUS_USER_ORGA_ID));
            $newStatementMeta->setSubmitUId(User::ANONYMOUS_USER_ID);
            $newStatementMeta->setOrgaName(User::ANONYMOUS_USER_ORGA_NAME);
            $newStatementMeta->setOrgaDepartmentName(User::ANONYMOUS_USER_DEPARTMENT_NAME);
            $newStatementMeta->setMiscDataValue(StatementMeta::SUBMITTER_ROLE, 'citizen');
        } else {
            $newStatementMeta->setOrgaName($statementData['Institution'] ?? '');
            $newStatementMeta->setOrgaDepartmentName($statementData['Abteilung'] ?? '');
            $newStatementMeta->setMiscDataValue(StatementMeta::SUBMITTER_ROLE, 'publicagency');
        }

        $newOriginalStatement->setManual();

        $inputSubmitType = $statementData[self::SUBMIT_TYPE_COLUMN] ?? self::SUBMIT_TYPE_UNKNOWN_TRANSLATED_UC;
        $this->setSubmitType($inputSubmitType, $newOriginalStatement, $line, $currentWorksheetTitle);

        $newOriginalStatement->setInternId($statementData['Eingangsnummer']);
        $newOriginalStatement->setMemo($statementData['Memo'] ?? '');

        // necessary to check incoming date-string:
        // use symfony forms + kleiner service um validator zu bauen um die folgene zeile zu vermeiden:
        //        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $this->validator->validate($statementData['Einreichungsdatum'], [new DateStringConstraint()]);
        if (0 === $violations->count()) {
            $newOriginalStatement->setSubmit(Carbon::parse($statementData['Einreichungsdatum'])->toDate());
        } else {
            $this->addImportViolations($violations, $line, $currentWorksheetTitle);
        }

        $statementText = $this->getValidatedStatementText($statementData[self::STATEMENT_TEXT] ?? '', $line, $currentWorksheetTitle);
        $newOriginalStatement->setText($statementText);
        $newOriginalStatement->setProcedure($currentProcedure);
        $newStatementMeta->setAuthorName($statementData['Name'] ?? '');
        $newStatementMeta->setSubmitName($statementData['Name'] ?? '');
        $newStatementMeta->setOrgaCity($statementData['Ort'] ?? '');
        $newStatementMeta->setOrgaPostalCode($statementData['PLZ'] ?? '');
        $newStatementMeta->setOrgaEmail($statementData['E-Mail'] ?? '');
        $newStatementMeta->setOrgaStreet($statementData['Straße'] ?? '');
        $newStatementMeta->setHouseNumber((string) ($statementData['Hausnummer'] ?? ''));

        $violations = $this->validator->validate($statementData['Verfassungsdatum'], new DateStringConstraint());
        if (0 === $violations->count()) {
            $dateString = $statementData['Verfassungsdatum'];
            $dateString = null == $dateString ? null : Carbon::parse($dateString)->toDate();
            $newStatementMeta->setAuthoredDate($dateString);
        } else {
            $this->addImportViolations($violations, $line, $currentWorksheetTitle);
        }

        $newStatementMeta->setSubmitOrgaId($this->currentUser->getUser()->getOrganisationId());

        $externId = $this->statementService->getNextValidExternalIdForProcedure(
            $currentProcedure->getId(),
            true,
            $offset
        );
        $newOriginalStatement->setExternId($externId);

        // always use standard statementElement for now:
        $statementElement = $this->elementsService->getStatementElement($currentProcedure->getId());
        $newOriginalStatement->setElement($statementElement);
        $newOriginalStatement->setPhase($newOriginalStatement->getProcedure()->getPhase());

        // not supported:
        // county, priorityArea, municipalities, tags, voters, headstatement, recommendation, housenumber,
        // attachements, paragaph, polygon, feedback, documents, publication

        $newOriginalStatement->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);
        $newOriginalStatement->setMeta($newStatementMeta);

        $gdprConsent = new GdprConsent();
        $gdprConsent->setStatement($newOriginalStatement);
        $newOriginalStatement->setGdprConsent($gdprConsent);

        return $newOriginalStatement;
    }

    public function mapSubmitType(string $incomingSubmitType): string
    {
        // use translation? (translation keys in form_options)
        if (\array_key_exists($incomingSubmitType, self::SUBMIT_TYPE_MAPPING)) {
            return self::SUBMIT_TYPE_MAPPING[$incomingSubmitType];
        }

        throw new UnexpectedValueException("Invalid submit type: $incomingSubmitType");
    }

    /**
     * @return Segment[]
     */
    public function getGeneratedSegments(): array
    {
        return $this->generatedSegments;
    }

    /**
     * Get first row values with optimized column range.
     *
     * @return array{0: array, 1: string} [column values, highest column letter]
     */
    protected function getFirstRowOfWorksheetWithHighestColumn(Worksheet $worksheet): array
    {
        $highestDataColumn = $this->getActualHighestDataColumn($worksheet);
        $firstRow = $worksheet->getRowIterator(1, 1)->current();
        $cellIterator = $firstRow->getCellIterator('A', $highestDataColumn);

        $values = [];
        foreach ($cellIterator as $cell) {
            $values[] = $cell->getValue();
        }

        return [$values, $highestDataColumn];
    }

    protected function getFirstRowOfWorksheet(Worksheet $worksheet): array
    {
        [$values] = $this->getFirstRowOfWorksheetWithHighestColumn($worksheet);

        return $values;
    }

    private function findOrCreateMiscTagTopic(): TagTopic
    {
        $procedure = $this->currentProcedureService->getProcedureWithCertainty();

        // Check if Sonstiges-Topic exists, otherwise create it
        $miscTopic = $this->tagService->findOneTopicByTitle(TagTopic::TAG_TOPIC_MISC, $procedure->getId());

        if (!$miscTopic instanceof TagTopic) {
            // create new Topic
            $miscTopic = $this->tagService->createTagTopic(
                TagTopic::TAG_TOPIC_MISC,
                $procedure,
                false
            );

            // Persist the TagTopic immediately so it's managed
            // Without this, when tags are persisted with cascade persist to TagTopic,
            // Doctrine tries to persist an unmanaged TagTopic that references Procedure,
            // but TagTopic→Procedure doesn't have cascade persist, causing flush to fail
            $this->entityManager->persist($miscTopic);
        }

        return $miscTopic;
    }

    protected function setSubmitType(string $inputSubmitType, Statement $statement, int $line, string $worksheetTitle): void
    {
        $this->validateSubmitType($inputSubmitType, $line, $worksheetTitle);
        $mappedSubmitType = $this->mapSubmitType($inputSubmitType);
        $statement->setSubmitType($mappedSubmitType);
    }

    private function getSubmitTypePattern(): string
    {
        // Handling the whitespace needs special attention: first it is removed from the
        // `implode` pattern building and afterwards appended to the pattern as `|^$` (read:
        // "or any line that ends right after it starts").
        $translatedSubmitTypes = array_keys(self::SUBMIT_TYPE_MAPPING);
        $pattern = implode('|', array_diff($translatedSubmitTypes, ['']));

        return "/(^($pattern)$)|(^$)/";
    }

    /**
     * Get the first {@link Tag} entity with a title and procedure matching the given one.
     *
     * Searches in {@link ExcelImporter::$generatedTags} first and of no matching entity is
     * found the database is searched.
     *
     * @throws PathException
     */
    private function getMatchingTag(string $tagTitle, string $procedureId): ?Tag
    {
        $tagTitleLower = mb_strtolower($tagTitle);

        // First, search in generatedTags array (tags created during this import) - CASE-INSENSITIVE
        foreach ($this->generatedTags as $tag) {
            $topic = $tag->getTopic();
            if (null !== $topic
                && $topic->getProcedure()?->getId() === $procedureId
                && mb_strtolower($tag->getTitle()) === $tagTitleLower) {
                return $tag;
            }
        }

        // If not found, check EntityManager's UnitOfWork for tags that are persisted but not yet flushed - CASE-INSENSITIVE
        $uow = $this->entityManager->getUnitOfWork();
        $scheduledInsertions = $uow->getScheduledEntityInsertions();

        foreach ($scheduledInsertions as $entity) {
            if ($entity instanceof Tag && mb_strtolower($entity->getTitle()) === $tagTitleLower) {
                $topic = $entity->getTopic();
                if (null !== $topic && $topic->getProcedure()?->getId() === $procedureId) {
                    return $entity;
                }
            }
        }

        // If still not found, query the database (already case-insensitive due to utf8mb3_unicode_ci collation)
        $titleCondition = $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyHasValue($tagTitle, ['title']),
            $this->conditionFactory->propertyHasValue($procedureId, ['topic', 'procedure', 'id']),
        );
        $matchingTags = $this->tagResourceType->getEntities([$titleCondition], []);

        if ([] !== $matchingTags) {
            return $matchingTags[0];
        }

        return null;
    }

    protected function getValidatedStatementText(
        string $statementText,
        int $line,
        string $currentWorksheetTitle,
    ): string {
        $violations = $this->validator->validate($statementText, $this->getStatementTextConstraint());
        if (0 !== $violations->count()) {
            $this->addImportViolations($violations, $line, $currentWorksheetTitle);
        }

        return $this->replaceLineBreak($statementText);
    }

    /**
     * Processes the 'weitere Einreichende' worksheet to create ProcedurePerson relations
     * for statements that were already processed.
     *
     * @throws MissingExcelDataException
     * @throws InvalidArgumentException
     */
    private function processWeitereEinreichende(Worksheet $worksheet): void
    {
        $similarStatementSubmitterParams = $worksheet->toArray();
        // cuts and extracts the firs line of the worksheet-array
        $columnNames = array_shift($similarStatementSubmitterParams);

        if (0 === count($similarStatementSubmitterParams)) {
            return; // No data in worksheet
        }

        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            throw new InvalidArgumentException('Current procedure is missing.');
        }

        foreach ($similarStatementSubmitterParams as $personData) {
            if ($this->isEmpty($personData)) {
                // skip empty lines in worksheet
                continue;
            }
            $personData = array_combine($columnNames, $personData);
            $this->processWeitereEinreichendeEntry($personData, $currentProcedure);
        }
    }

    /**
     * Processes a single 'weitere Einreichende' entry and creates ProcedurePerson relation.
     *
     * @param array<string, mixed> $personData
     *
     * @throws MissingExcelDataException
     * @throws InvalidArgumentException
     */
    private function processWeitereEinreichendeEntry(array $personData, ProcedureInterface $currentProcedure): void
    {
        $referenceStatementId = $personData['ReferenzStatement'] ?? null;
        $fullName = $personData['Name'] ?? null;
        $emailAddress = $personData['E-Mail'] ?? null;

        // Validate required fields
        if (empty($referenceStatementId)) {
            $message = 'ReferenzStatement is required in weitere Einreichende worksheet';
            $this->logger->error($message);
            throw new MissingExcelDataException($message);
        }

        if (empty($fullName)) {
            $message = 'Name is required in weitere Einreichende worksheet';
            $this->logger->error($message);
            throw new MissingExcelDataException($message);
        }
        if (empty($emailAddress)) {
            $message = 'Email address is required in weitere Einreichende worksheet';
            $this->logger->error($message);
            throw new MissingExcelDataException($message);
        }

        // Find corresponding statement
        $statement = $this->excelIdToStatementMapping[$referenceStatementId] ?? null;
        if (null === $statement) {
            $message = 'weitere Einreichende: Statement with ID '.$referenceStatementId.' not found in mapping';
            $this->logger->error($message);
            throw new InvalidArgumentException($message);
        }

        // Create ProcedurePerson
        $procedurePerson = new ProcedurePerson($fullName, $currentProcedure);

        // Set optional contact information (only fields available in 'weitere Einreichende' template)
        $procedurePerson->setStreetName($personData['Straße'] ?? null);
        $procedurePerson->setStreetNumber($personData['Hausnummer'] ?? null);
        $procedurePerson->setPostalCode($personData['PLZ'] ?? null);
        $procedurePerson->setCity($personData['Ort'] ?? null);

        // Add bidirectional relation
        $statement->addSimilarStatementSubmitter($procedurePerson);
        $this->entityManager->persist($statement);
    }

    protected function getSubmitTypeConstraint(string $inputSubmitType): Constraint
    {
        $translatedSubmitTypes = array_keys(self::SUBMIT_TYPE_MAPPING);
        $violationMessage = $this->translator->trans(
            'segments.import.error.submitType',
            [
                'translatedSubmitTypes' => implode(', ', array_diff($translatedSubmitTypes, [''])),
                'value'                 => $inputSubmitType,
            ]
        );

        return new Regex([
            'pattern' => $this->getSubmitTypePattern(),
            'message' => $violationMessage,
        ]);
    }

    /**
     * Process a single statement row from the Excel worksheet.
     * Returns the number of segments created (0 if statement was not processed).
     */
    private function processStatementRow(
        $row,
        StatementProcessingContext $context,
        array $segments,
        TagTopic $miscTopic,
        int $statementLine,
        SegmentExcelImportResult $result,
    ): int {
        // Memory optimization: Use actual highest data column from context
        $statementIterator = $row->getCellIterator('A', $context->highestDataColumn);
        $statement = array_map(static fn (Cell $cell) => $cell->getValue(), \iterator_to_array($statementIterator));

        if ($this->isEmpty($statement)) {
            return 0;
        }

        $statement = \array_combine($context->columnNamesMeta, $statement);
        $statement[self::PUBLIC_STATEMENT] = $this->getPublicStatement($statement['Typ'] ?? self::PUBLIC);

        $idConstraints = $this->validator->validate($statement[self::STATEMENT_ID], $this->notNullConstraint);
        if (0 !== $idConstraints->count()) {
            $result->addErrors($idConstraints, $statementLine, $context->statementWorksheetTitle);
            $statement[self::STATEMENT_ID] = 0;
        }

        $statementId = $statement[self::STATEMENT_ID];
        $correspondingSegments = $segments[$statementId] ?? [];

        $idMatchViolations = $this->validator->validate(
            $statement,
            new MatchingFieldValueInSegments($segments, $context->statementWorksheetTitle, $context->segmentWorksheetTitle)
        );
        if (0 !== $idMatchViolations->count()) {
            $result->addErrors($idMatchViolations, $statementLine, $context->statementWorksheetTitle.' + '.$context->segmentWorksheetTitle);
        }

        // This is a segment import. If there are statements without segments, ignore them
        if (0 === count($correspondingSegments)) {
            return 0;
        }

        $text = $this->htmlSanitizerService->escapeDisallowedTags(implode(' ', array_column($correspondingSegments, 'Einwand')));
        $statement[self::STATEMENT_TEXT] = $text;

        $generatedOriginalStatement = $this->createNewOriginalStatement($statement, $result->getStatementCount(), $statementLine, $context->statementWorksheetTitle);
        $generatedStatement = $this->createCopy($generatedOriginalStatement, flush: false);

        $violations = $this->statementValidator->validate($generatedStatement, [Statement::IMPORT_VALIDATION]);
        if (0 !== $violations->count()) {
            $result->addErrors($violations, $statementLine, $context->statementWorksheetTitle);

            return 0;
        }

        $result->addStatement($generatedStatement);

        $segmentsCreated = $this->createAndValidateSegmentsForStatement(
            $generatedStatement,
            $correspondingSegments,
            $context->segmentWorksheetTitle,
            $miscTopic,
            $result
        );

        if (0 === $context->processedStatements % 100) {
            $this->logger->info('[ExcelImporter] Statement processing progress', [
                'statements_processed' => $context->processedStatements,
                'duration_sec'         => round(microtime(true) - $context->step, 2),
                'memory_mb'            => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);
        }

        return $segmentsCreated;
    }

    /**
     * Create and validate all segments for a given statement.
     * Returns the number of segments successfully created.
     */
    private function createAndValidateSegmentsForStatement(
        Statement $statement,
        array $correspondingSegments,
        string $segmentWorksheetTitle,
        TagTopic $miscTopic,
        SegmentExcelImportResult $result,
    ): int {
        $counter = 1;
        $segmentsCreated = 0;

        foreach ($correspondingSegments as $segmentData) {
            $generatedSegment = $this->generateSegment(
                $statement,
                $segmentData,
                $counter,
                $segmentData['segment_line'],
                $segmentWorksheetTitle,
                $miscTopic
            );

            $violations = $this->segmentValidator->validate($generatedSegment, Segment::VALIDATION_GROUP_IMPORT);

            if (0 === $violations->count()) {
                $result->addSegment($generatedSegment);
                $this->entityManager->persist($generatedSegment);
                ++$segmentsCreated;
            } else {
                $result->addErrors($violations, $segmentData['segment_line'], $segmentWorksheetTitle);
            }

            ++$counter;
        }

        return $segmentsCreated;
    }
}
