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
use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterHandleSegmentsEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterPrePersistSegmentsEventInterface;
use demosplan\DemosPlanCoreBundle\Constraint\DateStringConstraint;
use demosplan\DemosPlanCoreBundle\Constraint\MatchingFieldValueInSegments;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Event\Statement\ExcelImporterHandleSegmentsEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\ExcelImporterPrePersistSegmentsEvent;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTitleException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use demosplan\DemosPlanCoreBundle\Validator\StatementValidator;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\PathException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

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

    /**
     * @var Segment[]
     */
    private $generatedSegments = [];

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
     */
    public function process(SplFileInfo $workbook): void
    {
        $this->generatedStatements = [];
        $this->errors = [];
        $worksheets = $this->extractWorksheets($workbook, 1);

        foreach ($worksheets as $worksheet) {
            $currentWorksheetTitle = $worksheet->getTitle() ?? '';
            // Exclude legend from iteration as it should not be processed
            if ('Legende' === $currentWorksheetTitle) {
                continue;
            }
            $publicStatement = $this->getPublicStatement($currentWorksheetTitle);
            $statementData = $worksheet->toArray();
            $columnNames = array_shift($statementData);
            if (0 === count($statementData)) {
                throw new MissingDataException('No data in rows found.');
            }
            foreach ($statementData as $line => $statement) {
                if ($this->isEmpty($statement)) {
                    continue;
                }
                $statement = \array_combine($columnNames, $statement);
                $statement[self::PUBLIC_STATEMENT] = $publicStatement;

                $generatedOriginalStatement = $this->createNewOriginalStatement($statement, count($this->generatedStatements), $line, $currentWorksheetTitle);
                // no validation of $generatedOriginalStatement?

                $generatedStatement = $this->createCopy($generatedOriginalStatement);

                $constraints = $this->statementValidator->validate($generatedStatement, [Statement::IMPORT_VALIDATION]);
                if (0 === $constraints->count()) {
                    $this->generatedStatements[] = $generatedStatement;
                } else {
                    $this->addImportViolations($constraints, $line, $currentWorksheetTitle);
                }
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
     */
    public function processSegments(SplFileInfo $fileInfo): SegmentExcelImportResult
    {
        $result = new SegmentExcelImportResult();

        if (!$this->currentUser->hasPermission('feature_segment_recommendation_edit')) {
            throw new AccessDeniedException('Current user is not permitted to create or edit segments.');
        }

        [$segmentsWorksheet, $metaDataWorksheet] = $this->getSegmentImportWorksheets($fileInfo);

        $segmentWorksheetTitle = $this->getTitle($segmentsWorksheet);
        $segments = $this->getGroupedSegmentsFromWorksheet($segmentsWorksheet, $result);

        unset($segmentsWorksheet);

        // FIXME: This event has to be distaptched only when the field 'Schlagworte' does not exist. Have to check
        // FIXME: that to make sure the event will be disptached only when it have to be.

        $event = $this->dispatcher->dispatch(
            new ExcelImporterHandleSegmentsEvent($segments),
            ExcelImporterHandleSegmentsEventInterface::class
        );

        $segments = $event->getSegments();

        $miscTopic = $this->findOrCreateMiscTagTopic();

        $columnNamesMeta = $this->getFirstRowOfWorksheet($metaDataWorksheet);
        $statementWorksheetTitle = $metaDataWorksheet->getTitle() ?? '';

        foreach ($metaDataWorksheet->getRowIterator(2) as $statementLine => $row) {
            $statementIterator = $row->getCellIterator('A', $metaDataWorksheet->getHighestColumn());
            $statement = array_map(static fn (Cell $cell) => $cell->getValue(), \iterator_to_array($statementIterator));

            if ($this->isEmpty($statement)) {
                continue;
            }

            $statement = \array_combine($columnNamesMeta, $statement);
            $statement[self::PUBLIC_STATEMENT] = $this->getPublicStatement($statement['Typ'] ?? self::PUBLIC);

            $idConstraints = $this->validator->validate($statement[self::STATEMENT_ID], $this->notNullConstraint);
            if (0 !== $idConstraints->count()) {
                $result->addErrors($idConstraints, $statementLine, $statementWorksheetTitle);
                $statement[self::STATEMENT_ID] = 0;
            }

            $statementId = $statement[self::STATEMENT_ID];
            $correspondingSegments = $segments[$statementId] ?? [];

            $idMatchViolations = $this->validator->validate(
                $statement,
                new MatchingFieldValueInSegments($segments, $statementWorksheetTitle, $segmentWorksheetTitle)
            );
            if (0 !== $idMatchViolations->count()) {
                $result->addErrors($idMatchViolations, $statementLine, $statementWorksheetTitle.' + '.$segmentWorksheetTitle);
            }

            // This is a segment import. If there are statements without segments, ignore them
            if (0 === count($correspondingSegments)) {
                continue;
            }

            $statement[self::STATEMENT_TEXT] = implode(' ', array_column($correspondingSegments, 'Einwand'));

            $generatedOriginalStatement = $this->createNewOriginalStatement($statement, $result->getStatementCount(), $statementLine, $statementWorksheetTitle);
            $generatedStatement = $this->createCopy($generatedOriginalStatement);

            $violations = $this->statementValidator->validate($generatedStatement, [Statement::IMPORT_VALIDATION]);
            if (0 === $violations->count()) {
                $result->addStatement($generatedStatement);
            } else {
                $result->addErrors($violations, $statementLine, $statementWorksheetTitle);
            }

            // create all corresponding segments
            $counter = 1;
            foreach ($correspondingSegments as $segmentData) {
                $generatedSegment = $this->generateSegment($generatedStatement, $segmentData, $counter, $segmentData['segment_line'], $segmentWorksheetTitle, $miscTopic);

                // validate segment
                $violations = $this->segmentValidator->validate($generatedSegment, Segment::VALIDATION_GROUP_IMPORT);

                if (0 === $violations->count()) {
                    $result->addSegment($generatedSegment);

                    // needs to be persisted to make the PrePersistUniqueInternIdConstraint work
                    $this->entityManager->persist($generatedSegment);
                } else {
                    $result->addErrors($violations, $segmentData['segment_line'], $segmentWorksheetTitle);
                }

                ++$counter;
            }

            unset($segments[$statementId]);
        }

        $this->dispatcher->dispatch(
            new ExcelImporterPrePersistSegmentsEvent($result->getSegments()),
            ExcelImporterPrePersistSegmentsEventInterface::class
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
     * Iterates through the given {@link Worksheet} and transfers each row into an array containing data for one segment.
     *
     * Does not create entities or accesses the database in any way.
     *
     * @return array<string, array<int, array<string, mixed>>> an array of segments grouped by their statement IDs
     */
    private function getGroupedSegmentsFromWorksheet(Worksheet $segmentsWorksheet, SegmentExcelImportResult $result): array
    {
        $columnNamesSegments = $this->getFirstRowOfWorksheet($segmentsWorksheet);
        $segmentsWorksheetTitle = $this->getTitle($segmentsWorksheet);

        $segments = [];
        foreach ($segmentsWorksheet->getRowIterator(2) as $segmentLine => $row) {
            $segmentIterator = $row->getCellIterator('A', $segmentsWorksheet->getHighestColumn());
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
        return empty(
            array_filter(
                $input,
                static fn ($field) => null !== $field && (!is_string($field) || '' !== trim($field))
            )
        );
    }

    /**
     * @throws AccessDeniedException
     * @throws PathException
     * @throws UserNotFoundException
     * @throws DuplicatedTagTitleException
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
        $segment->setPlace($this->placeService->findFirstOrderedBySortIndex($procedure->getId()));
        $segment->setCreated(new DateTime());
        $segment->setOrderInProcedure($counter);

        // Handle Tags
        if ('' !== $segmentData['Schlagworte'] && null !== $segmentData['Schlagworte']) {
            $procedureId = $statement->getProcedure()->getId();
            $tagTitlesString = $segmentData['Schlagworte'];
            if (is_numeric($tagTitlesString)) {
                $tagTitlesString = (string) $tagTitlesString;
            }
            $tagTitles = explode(',', (string) $tagTitlesString);

            foreach ($tagTitles as $tagTitle) {
                $matchingTag = $this->getMatchingTag($tagTitle, $procedureId);

                $createNewTag = null === $matchingTag;
                if ($createNewTag) {
                    $matchingTag = $this->tagService->createTag(trim($tagTitle), $miscTopic, false);
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

        if (null === $currentProcedure) {
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

        $statementText = $this->getValidatedStatementText($statementData[self::STATEMENT_TEXT], $line, $currentWorksheetTitle);
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

    protected function getFirstRowOfWorksheet(Worksheet $worksheet): array
    {
        $rowData = $worksheet->rangeToArray('A1:'.$worksheet->getHighestColumn().'1');

        return $rowData[0];
    }

    private function findOrCreateMiscTagTopic(): TagTopic
    {
        $procedure = $this->currentProcedureService->getProcedureWithCertainty();

        // Check if Sonstiges-Topic exists, otherwise create it
        $miscTopic = $this->tagService->findOneTopicByTitle(TagTopic::TAG_TOPIC_MISC, $procedure->getId());

        if (null === $miscTopic) {
            // create new Topic
            $miscTopic = $this->tagService->createTagTopic(
                TagTopic::TAG_TOPIC_MISC,
                $procedure,
                false
            );
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
        $titleCondition = $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyHasValue(trim($tagTitle), $this->tagResourceType->title),
            $this->conditionFactory->propertyHasValue($procedureId, $this->tagResourceType->topic->procedure->id),
        );

        $matchingTags = $this->tagResourceType->listPrefilteredEntities($this->generatedTags, [$titleCondition]);
        if ([] === $matchingTags) {
            $matchingTags = $this->tagResourceType->getEntities([$titleCondition], []);
        }

        return $matchingTags[0] ?? null;
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
}
