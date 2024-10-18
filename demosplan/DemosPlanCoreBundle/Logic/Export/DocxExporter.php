<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ViewOrientation;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresTranslatorTrait;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\StatementHandlingResult;
use Exception;
use Illuminate\Support\Collection;
use Monolog\Logger;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DocxExporter
{
    use RequiresTranslatorTrait;

    final public const EXPORT_SORT_BY_PARAGRAPH_FRAGMENTS_ONLY = 'byParagraphFragmentsOnly';
    final public const EXPORT_SORT_BY_PARAGRAPH = 'byParagraph';
    final public const EXPORT_SORT_DEFAULT = 'default';
    /**
     * @var array Style, wie Tabelle im gesamten aussehen soll
     */
    protected $tableStyle = [
        'borderColor' => '000000',
        'borderSize'  => 6,
        'cellMargin'  => 80,
    ];

    /** @var array Definition der Stile der Header der Tabelle */
    protected $firstRowStyle = [
        'bold'       => true,
        'valign'     => 'center',
        'tableAlign' => 'center',
        'tblHeader'  => true,
        'cantSplit'  => true,
        'spaceAfter' => 0,
    ];

    /**
     * @var array Bestimmte Zellen
     */
    protected $cellHCentered = ['valign' => 'center', 'spaceAfter' => 0];

    /**
     * @var StatementService
     */
    protected $statementService;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var ServiceImporter
     */
    protected $serviceImport;

    /**
     * @var Environment
     */
    protected $twig;

    protected $logger;

    /**
     * @var ProcedureHandler
     */
    protected $procedureHandler;

    /** @var MessageBag */
    protected $messageBag;

    /** @var GlobalConfigInterface */
    protected $config;

    /**
     * @var FormFactory
     */
    protected $formFactory;

    /** @var ValidatorInterface */
    protected $validator;
    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    public function __construct(
        private readonly EditorService $editorService,
        private readonly FieldDecider $exportFieldDecider,
        FileService $fileService,
        GlobalConfigInterface $config,
        LoggerInterface $logger,
        protected readonly MapService $mapService,
        PermissionsInterface $permissions,
        private readonly StatementFragmentService $statementFragmentService,
        private readonly StatementHandler $statementHandler,
        StatementService $statementService,
        TranslatorInterface $translator,
    ) {
        $this->config = $config;
        $this->fileService = $fileService;
        $this->statementService = $statementService;
        $this->translator = $translator;
        $this->permissions = $permissions;
        $this->logger = $logger;
    }

    /**
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function generateDocx(
        StatementHandlingResult $outputResult,
        string $templateName,
        bool $anonym,
        bool $numberStatements,
        string $exportType,
        ViewOrientation $viewOrientation,
        array $requestPost,
        string $sortType,
        string $viewMode = AssessmentTableViewMode::DEFAULT_VIEW,
    ): WriterInterface {
        /**
         * I tried to use templates with PHPWord 0.13.0, but it is not possible
         * to clone Tables and to use structures like Headings in Table.
         * Therefore this workaround with similar but project specific creation of
         * documents.
         */
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        $incomingStatements = $outputResult->getStatements();
        $procedure = $this->getProcedureHandler()->getProcedureWithCertainty($outputResult->getProcedure()['id']);
        if ('condensed' === $templateName) {
            switch ($sortType) {
                case self::EXPORT_SORT_BY_PARAGRAPH:
                    $includeFragmentsInStatementExport = 'statementsAndFragments' === $exportType;
                    $incomingNonOriginalStatements = array_filter(
                        $incomingStatements,
                        static fn (array $statement) =>
                            // True if the given statement is not an original statement.
                            // False otherwise.
                            // Original statements are non-moved statements without a parentId.
                            isset($statement['movedFromProcedureId']) || isset($statement['parentId'])
                    );
                    $relevantFragmentIds = $includeFragmentsInStatementExport
                        // if fragments are to be included in the export then get their IDs from the statements
                        ? $this->getSelectedFragmentIdsOfStatements($incomingNonOriginalStatements)
                        // otherwise ignore the fragments
                        : [];
                    $relevantStatements = $includeFragmentsInStatementExport
                        // if fragments are to be included in the export then only export statements without fragments
                        ? array_filter(
                            $incomingNonOriginalStatements,
                            static fn (array $statement) => 0 === (is_countable($statement['fragments']) ? count($statement['fragments']) : 0)
                        )
                        // otherwise include all statements
                        : $incomingNonOriginalStatements;
                    // get the IDs of the relevant statements
                    $relevantStatementIds = array_unique(array_column($relevantStatements, 'id'));

                    $groupStructure = $this->statementService->createElementsGroupStructure(
                        $procedure->getId(),
                        $relevantStatementIds,
                        $relevantFragmentIds
                    );

                    $orientation = ViewOrientation::createLandscape();

                    /**
                     * @param Section                         $section
                     * @param Statement[]|StatementFragment[] $items
                     */
                    $entriesRenderFunction = function (Section $section, array $items) use ($anonym, $orientation, $exportType) {
                        $styles = $this->getDefaultDocxPageStyles($orientation);
                        $table = $section->addTable($styles['tableStyle']);

                        $typeHeader = $this->translator->trans('statement');
                        if ('statementsOnly' !== $exportType) {
                            $typeHeader .= '/'.$this->translator->trans('fragment');
                        }

                        // Adds headers to every page of table
                        $this->addCondensedTableHeaders($styles, $table, $typeHeader);

                        foreach ($items as $item) {
                            /** @var Statement|StatementFragment $item */
                            if ($item instanceof StatementFragment) {
                                $item = $this->formatFragmentObject($item);
                            } elseif ($item instanceof Statement) {
                                $item = $this->formatStatementObject($item);
                            } else {
                                $type = gettype($item);
                                throw new InvalidArgumentException("invalid type given: {$type}");
                            }
                            $this->renderTableItem(
                                $table,
                                $item,
                                $anonym,
                                $orientation,
                                $exportType
                            );
                        }
                    };

                    $objWriter = $this->createDocxGrouped(
                        $procedure,
                        $groupStructure,
                        $orientation,
                        $phpWord,
                        $entriesRenderFunction
                    );
                    break;
                case self::EXPORT_SORT_BY_PARAGRAPH_FRAGMENTS_ONLY:
                    $relevantFragmentIds = $this->getSelectedFragmentIdsOfStatements($incomingStatements);
                    // 'items' may contain IDs of statements and/or fragments, get the fragment ones only
                    $selections = array_filter($requestPost['items'], fn (string $id) => null === $this->statementHandler->getStatement($id));
                    // if specific fragments were selected use these only
                    if (0 !== count((array) $selections)) {
                        // create a mapping from ID to ID
                        $selections = array_combine($selections, $selections);
                        // filter out non-selected items
                        $relevantFragmentIds = array_filter($relevantFragmentIds, static fn (string $fragmentId) => array_key_exists($fragmentId, $selections));
                    }
                    $groupStructure = $this->statementService->createElementsGroupStructure(
                        $procedure->getId(),
                        [],
                        $relevantFragmentIds
                    );

                    $orientation = ViewOrientation::createLandscape();

                    /**
                     * @param Section             $section
                     * @param StatementFragment[] $items
                     */
                    $entriesRenderFunction = function (Section $section, array $items) use ($anonym, $orientation) {
                        $translator = $this->translator;
                        $styles = $this->getDefaultDocxPageStyles($orientation);
                        // adjust the default to get the intended table in Microsoft Word
                        $styles['cellStyleStatementDetails']['gridSpan'] = 1;
                        $assessmentTable = $section->addTable($styles['tableStyle']);

                        // Adds headers to every page of table
                        $this->addCondensedTableHeaders($styles, $assessmentTable, $translator->trans('fragment'));

                        foreach ($items as $item) {
                            $fragmentRow = $assessmentTable->addRow(null);
                            $metaInfoCell = $fragmentRow->addCell(
                                $styles['cellWidthTotal'] * 0.12,
                                $styles['cellStyleStatementDetails']
                            );

                            // SN von Töbs
                            $statementMeta = $item->getStatement()->getMeta();
                            $publicStatementString = $item->getStatement()->getPublicStatement();
                            switch ($publicStatementString) {
                                case Statement::EXTERNAL:
                                    // SN von Bürgern
                                    $metaInfoCell->addText(
                                        $translator->trans('public'),
                                        $styles['textStyleStatementDetails'],
                                        $styles['textStyleStatementDetailsParagraphStyles']
                                    );

                                    if (!$anonym && '' !== $statementMeta->getAuthorName()) {
                                        $metaInfoCell->addText(
                                            $this->translator->trans('name').': ',
                                            $styles['textStyleStatementDetails'],
                                            $styles['textStyleStatementDetailsParagraphStyles']
                                        );
                                        $metaInfoCell->addText(
                                            $statementMeta->getAuthorName(),
                                            $styles['textStyleStatementDetails'],
                                            $styles['textStyleStatementDetailsParagraphStyles']
                                        );
                                    }

                                    break;
                                case Statement::INTERNAL:
                                    $metaInfoCell->addText(
                                        $translator->trans('institution').':',
                                        $styles['textStyleStatementDetails'],
                                        $styles['textStyleStatementDetailsParagraphStyles']
                                    );

                                    $orgaName = $statementMeta->getOrgaName();
                                    $orgaName = (null === $orgaName || '' === $orgaName)
                                        ? $translator->trans('not.specified')
                                        : $orgaName;

                                    // Abteilung
                                    $orgaDepartmentName = $statementMeta->getOrgaDepartmentName();
                                    if (0 < strlen($orgaDepartmentName)) {
                                        $orgaName .= ', '.$orgaDepartmentName;
                                    }

                                    $metaInfoCell->addText(
                                        $orgaName,
                                        $styles['textStyleStatementDetails'],
                                        $styles['textStyleStatementDetailsParagraphStyles']
                                    );

                                    break;
                                default:
                                    throw new InvalidArgumentException("Unknown public statement type: {$publicStatementString}");
                            }

                            $metaInfoCell->addText(
                                $this->getTranslator()->trans('id.statement').': '.$this->createExternIdStringFromObject($item->getStatement()),
                                $styles['textStyleStatementDetails'],
                                $styles['textStyleStatementDetailsParagraphStyles']
                            );

                            $textCell = $fragmentRow->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellTop']);
                            // T6679:
                            $fragmentText = $this->editorService->handleObscureTags($item->getText(), $anonym);
                            $this->addHtml($textCell, $fragmentText, $styles);
                            $considerationCell = $fragmentRow->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellTop']);
                            $consideration = $item->getConsideration();
                            $consideration = $this->editorService->handleObscureTags($consideration, $anonym);
                            $this->addHtml($considerationCell, $consideration, $styles);
                        }
                    };

                    $objWriter = $this->createDocxGrouped(
                        $procedure,
                        $groupStructure,
                        $orientation,
                        $phpWord,
                        $entriesRenderFunction
                    );

                    break;
                case self::EXPORT_SORT_DEFAULT:
                    $objWriter = $this->createDocxCondensed(
                        $procedure,
                        $incomingStatements,
                        $anonym,
                        $numberStatements,
                        ViewOrientation::createLandscape(),
                        $phpWord,
                        $exportType,
                        $requestPost
                    );
                    break;
                default:
                    throw new InvalidArgumentException("Unknown sort key: $sortType");
            }
        } else {
            $exportConfig = $procedure->getDefaultExportFieldsConfiguration();
            $styles = $this->getDefaultDocxPageStyles($viewOrientation);
            $phpWord->setDefaultFontSize(9);
            $section = $phpWord->addSection($styles['orientation']);
            $header = $section->addHeader();
            $orgaHeaderInfo = $this->exportFieldDecider->isExportable(FieldDecider::FIELD_ORGA_NAME, $exportConfig)
                ? $procedure->getOrgaName().' - '
                : '';

            $header->addText(
                $orgaHeaderInfo.$procedure->getName()
                .' - '.$this->translator->trans('considerationtable').', '
                .$this->translator->trans('date.created').' '.date('d.m.Y')
            );

            $phpWord->addTableStyle('assessmentTable', $this->tableStyle, $this->firstRowStyle);

            $statements = array_column($incomingStatements, 'id', 'id');
            $statementEntities = $this->statementService->getStatementsByIds(array_keys($statements));

            // keep sorting
            foreach ($statementEntities as $statementEntity) {
                $statements[$statementEntity->getId()] = $statementEntity;
            }

            if (AssessmentTableViewMode::DEFAULT_VIEW === $viewMode) {
                $objWriter = $this->createDocxUngrouped(
                    $statements,
                    $anonym,
                    $numberStatements,
                    $templateName,
                    $phpWord,
                    $viewOrientation,
                    $styles,
                    $section
                );
            } else {
                $missingGroupTitle = $this->getTranslator()->trans('priority.missing');
                if (AssessmentTableViewMode::ELEMENTS_VIEW === $viewMode) {
                    $group = $this->statementService->createElementsGroupStructureBobHH(
                        $procedure->getId(),
                        $statements,
                        $missingGroupTitle
                    );
                } elseif (AssessmentTableViewMode::TAG_VIEW === $viewMode) {
                    $group = $this->statementService->createTagsGroupStructure(
                        $statements,
                        $missingGroupTitle
                    );
                } else {
                    throw new InvalidArgumentException("unknown view mode: '{$viewMode}'");
                }
                $phpWord->addTitleStyle(2, ['size' => 18, 'color' => '000000']);
                $phpWord->addTitleStyle(3, ['size' => 16, 'color' => '444444']);
                $phpWord->addTitleStyle(4, ['size' => 14, 'color' => '888888']);
                $objWriter = $this->createDocxGroupedBobHH(
                    $group,
                    $anonym,
                    $numberStatements,
                    $templateName,
                    $phpWord,
                    $viewOrientation,
                    $styles,
                    $section
                );
            }
        }

        return $objWriter;
    }

    /**
     * @param ProcedureHandler $procedureHandler
     */
    public function setProcedureHandler($procedureHandler)
    {
        $this->procedureHandler = $procedureHandler;
    }

    /**
     * @return ProcedureHandler
     */
    protected function getProcedureHandler()
    {
        return $this->procedureHandler;
    }

    /**
     * @param array<int,array<string,mixed>> $statements
     *
     * @return array<int, string>
     */
    protected function getSelectedFragmentIdsOfStatements(array $statements): array
    {
        return collect($statements)
            ->pluck('fragments')
            ->flatMap($this->statementFragmentService->sortFragmentArraysBySortIndex(...))
            ->pluck('id')
            ->unique()
            ->all();
    }

    /**
     * Get default Docx Page Styles.
     */
    protected function getDefaultDocxPageStyles(ViewOrientation $orientation): array
    {
        $styles = [];
        // Benutze das ausgewählte Format
        $styles['orientation'] = [];
        // im Hochformat werden für LibreOffice anderen Breiten benötigt
        $styles['cellWidthTotal'] = 10000;
        $styles['firstCellWidth'] = 1500;
        $styles['cellWidth'] = 3850;
        $styles['cellWidthSecondThird'] = 7500;

        $tableStyle = $this->getDefaultDocxTableStyle();
        $styles['tableStyle'] = $tableStyle;
        $styles['cellStyleStatementDetails'] = ['gridSpan' => 2, 'bgColor' => 'f0f0f5', 'valign' => 'top'];
        $styles['textStyleStatementDetails'] = ['bold' => true];
        $styles['textStyleStatementDetailsParagraphStyles'] = ['spaceAfter' => 0];
        $styles['cellHeading'] = ['align' => 'center', 'valign' => 'center'];
        $styles['cellHeadingText'] = ['bold' => true, 'valign' => 'center', 'align' => 'center', 'name' => 'Arial', 'size' => 9];
        $styles['cellTop'] = ['valign' => 'top'];

        if ($orientation->isLandscape()) {
            $styles['cellWidthTotal'] = 14000;
            $styles['orientation'] = ['orientation' => 'landscape'];
            $styles['firstCellWidth'] = 2000;
            $styles['cellWidth'] = 6000;
            $styles['cellWidthSecondThird'] = 12000;
        }

        return $styles;
    }

    protected function getDefaultDocxTableStyle(): \PhpOffice\PhpWord\Style\Table
    {
        $tableStyle = new \PhpOffice\PhpWord\Style\Table();
        $tableStyle->setLayout(\PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED)
            ->setBorderColor($this->tableStyle['borderColor'])
            ->setBorderSize($this->tableStyle['borderSize'])
            ->setCellMargin($this->tableStyle['cellMargin']);

        return $tableStyle;
    }

    public function addCondensedTableHeaders(array $styles, Table $table, string $typeHeader): void
    {
        $table->addRow(null, ['tblHeader' => true]);
        $table->addCell($styles['cellWidthTotal'] * 0.12, $styles['cellHeading'])
            ->addText($this->translator->trans('submitter.data'), $styles['cellHeadingText'], $styles['textStyleStatementDetailsParagraphStyles']);
        $table->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellHeading'])
            ->addText($typeHeader, $styles['cellHeadingText'], $styles['textStyleStatementDetailsParagraphStyles']);
        $table->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellHeading'])
            ->addText($this->translator->trans('response'), $styles['cellHeadingText'], $styles['textStyleStatementDetailsParagraphStyles']);
    }

    /**
     * Fragment in unified data format.
     *
     * @return array - formatted fragment
     *
     * @throws Exception
     */
    public function formatFragmentObject(StatementFragment $fragment): array
    {
        $item = $this->formatStatementObject($fragment->getStatement());

        // override selected item fields with fragment content:
        $item['type'] = 'fragment';
        $item['created'] = $fragment->getCreated(); // todo: check format
        $item['recommendation'] = $fragment->getConsideration();
        $item['text'] = $fragment->getText();
        $item['elementId'] = $fragment->getElementId();
        $item['elementTitle'] = $fragment->getElementTitle();

        return $item;
    }

    /**
     * Statement in unified data format.
     *
     * @return array - formatted statement
     *
     * @throws ReflectionException
     *
     * @deprecated Use {@link formatStatementObject} instead
     */
    public function formatStatementArray(array $statement): array
    {
        return [
            'type'                      => 'statement',
            'attachments'               => $statement['attachments'] ?? null,
            'authoredDate'              => $statement['meta']['authoredDate'] ?? null,
            'cluster'                   => $statement['cluster'] ?? null,
            'documentTitle'             => $statement['document']['title'] ?? null,
            'externId'                  => $statement['externId'] ?? null,
            'formerExternId'            => $statement['formerExternId'] ?? null,
            'elementTitle'              => $statement['element']['title'] ?? null,
            'files'                     => $statement['files'] ?? null,
            'orgaName'                  => $statement['meta']['orgaName'] ?? null,
            'orgaDepartmentName'        => $statement['meta']['orgaDepartmentName'] ?? null,
            'originalId'                => $statement['original']['ident'] ?? null,
            'paragraphTitle'            => $statement['paragraph']['title'] ?? null,
            'parentId'                  => $statement['parent']['ident'] ?? null,
            'polygon'                   => $statement['polygon'] ?? null,
            'publicAllowed'             => $statement['publicAllowed'] ?? null,
            'publicCheck'               => $statement['publicCheck'] ?? null,
            'publicStatement'           => $statement['publicStatement'] ?? null,
            'publicVerified'            => $statement['publicVerified'] ?? null,
            'publicVerifiedTranslation' => $statement['publicVerifiedTranslation'] ?? null,
            'recommendation'            => $statement['recommendation'] ?? null,
            'submit'                    => $statement['submit'] ?? null,
            'submitName'                => $statement['meta']['submitName'] ?? null,
            'authorName'                => $statement['meta']['authorName'] ?? null,
            'text'                      => $statement['text'] ?? null,
            'votes'                     => $statement['votes'] ?? null,
            'votesNum'                  => $statement['votesNum'] ?? null,
            'likesNum'                  => $statement['likesNum'] ?? null,
            'fragments'                 => [],
            'userState'                 => $statement['meta']['userState'] ?? null,
            'userGroup'                 => $statement['meta']['userGroup'] ?? null,
            'userOrganisation'          => $statement['meta']['userOrganisation'] ?? null,
            'movedToProcedureName'      => $statement['movedToProcedureName'] ?? null,
            'movedFromProcedureName'    => $statement['movedFromProcedureName'] ?? null,
            'userPosition'              => $statement['meta']['userPosition'] ?? null,
            'isClusterStatement'        => $statement['isClusterStatement'] ?? null,
            'name'                      => $statement['name'] ?? null,
        ];
    }

    /**
     * Statement in unified data format.
     *
     * @return array formatted statement
     *
     * @throws ReflectionException
     */
    public function formatStatementObject(Statement $statement): array
    {
        $item = $this->statementService->convertToLegacy($statement);
        $item['parent'] = $this->statementService->convertToLegacy($statement->getParent());
        $item['original'] = $this->statementService->convertToLegacy($statement->getOriginal());

        return $this->formatStatementArray($item);
    }

    /**
     * If item is statement with fragments then it will render the fragments with the statement header (without
     * the statements original text).
     * <p>
     * If item is statement without fragments then it will render the statement header and statement text.
     * <p>
     * Fragments can be converted to statement like items using 'formatFragment()' function.
     *
     * @param Table  $assessmentTable
     * @param array  $item
     * @param bool   $anonymous
     * @param string $exportType
     *
     * @throws Exception
     */
    protected function renderTableItem(
        $assessmentTable,
        $item,
        $anonymous,
        ViewOrientation $orientation,
        $exportType,
        bool $numberStatements = false,
        int $statementNumber = 0,
    ): void {
        $styles = $this->getDefaultDocxPageStyles($orientation);

        if (null === $item['movedToProcedureName']) {
            // Stellungnahme oder Datensatz und Erwiderung
            if ('statementsAndFragments' === $exportType && 0 < (is_countable($item['fragments']) ? count($item['fragments']) : 0)) {
                $this->addFragmentRows($item, $assessmentTable, $styles['cellWidthTotal'] * 0.44, $styles['cellWidthTotal'] * 0.44, $styles, $anonymous);
            } else {
                $assessmentTable->addRow();
                // add submitterData cell
                $this->addSubmitterData(
                    $anonymous,
                    $assessmentTable,
                    $item,
                    $styles,
                    $numberStatements,
                    $statementNumber
                );
                $cell2 = $assessmentTable->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellTop']);
                if (isset($item['text'])) {
                    $item['text'] = $this->editorService->handleObscureTags($item['text'], $anonymous);
                    $this->addHtml($cell2, $item['text'], $styles);
                }

                $cell3 = $assessmentTable->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellTop']);
                if (isset($item['recommendation'])) {
                    $this->addHtml($cell3, $item['recommendation'], $styles);
                }
            }
        } else {
            // Moved Statement
            $assessmentTable->addRow();
            $this->addSubmitterData(
                $anonymous,
                $assessmentTable,
                $item,
                $styles,
                $numberStatements,
                $statementNumber
            );

            $movedStatementText =
                $this->translator->trans('statement.moved', ['name' => $item['movedToProcedureName']]);

            $assessmentTable->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellHeading'])
                ->addText($movedStatementText, $styles['cellHeadingText'], $styles['textStyleStatementDetailsParagraphStyles']);

            $assessmentTable->addCell($styles['cellWidthTotal'] * 0.44, $styles['cellHeading']);
        }
    }

    protected function addSubmitterData(
        bool $anonymous,
        Table $assessmentTable,
        array $item,
        array $styles,
        bool $numberStatements = false,
        int $statementNumber = 0,
        bool $fragmentShort = false,
    ): void {
        $translator = $this->translator;

        $metaInfoCell = $assessmentTable->addCell($styles['cellWidthTotal'] * 0.12, $styles['cellTop']);

        $isCluster = false;
        if (isset($item['cluster'])) {
            $isCluster = is_array($item['cluster']) && 0 < count($item['cluster']);
        } elseif (isset($item['isClusterStatement'])) {
            $isCluster = $item['isClusterStatement'];
        }

        if ($numberStatements) {
            $statementNumberText = $translator->trans('statement.nr').': '.$statementNumber;
            $metaInfoCell->addText(
                $statementNumberText,
                $styles['textStyleStatementDetails'],
                $styles['textStyleStatementDetailsParagraphStyles']
            );
        }

        // SN von Töbs
        if (!$isCluster && !$fragmentShort && isset($item['publicStatement'])) {
            if (Statement::INTERNAL === $item['publicStatement']) {
                $orgaName = ('' == $item['orgaName']) ? $translator->trans('not.specified') : $item['orgaName'];
                $institutionData = $translator->trans('institution').': '.$orgaName;

                // Abteilung
                if (0 < strlen((string) $item['orgaDepartmentName'])) {
                    $institutionData .= ', '.$item['orgaDepartmentName'];
                }
                if (false == $anonymous) {
                    $institutionData .= 0 < strlen((string) $item['submitName']) ? ': '.$item['submitName'] : '';
                }
                $metaInfoCell->addText(
                    $institutionData,
                    $styles['textStyleStatementDetails'],
                    $styles['textStyleStatementDetailsParagraphStyles']
                );
            } elseif (Statement::EXTERNAL === $item['publicStatement']) {
                // SN von Bürgern
                $orgaName = ('' == $item['orgaName']) ? $translator->trans('not.specified') : $item['orgaName'];
                $citizenData = $translator->trans('public').': '.$orgaName;

                if (false == $anonymous) {
                    // Name
                    $citizenData .= ', '.$item['authorName'];
                    // T454 address should not be exported
                }
                $metaInfoCell->addText(
                    $citizenData,
                    $styles['textStyleStatementDetails'],
                    $styles['textStyleStatementDetailsParagraphStyles']
                );
            }
        }

        // cluster
        if ($isCluster) {
            $metaInfoCell->addText(
                $translator->trans('cluster'),
                $styles['textStyleStatementDetails'],
                $styles['textStyleStatementDetailsParagraphStyles']
            );
        }

        $metaInfoCell->addText(
            $this->getIdString($item),
            $styles['textStyleStatementDetails'],
            $styles['textStyleStatementDetailsParagraphStyles']
        );

        // cluster
        if (isset($item['cluster']) && is_array($item['cluster']) && 0 < count($item['cluster'])) {
            $clusteredStatementMetaData = '('.$translator->trans('id.plural').': ';
            $clusteredStatementMetaData .= implode(', ', array_column($item['cluster'], 'externId'));
            $clusteredStatementMetaData .= ')';

            $metaInfoCell->addText(
                $translator->trans('quantity').': '.count($item['cluster']),
                $styles['textStyleStatementDetails'],
                $styles['textStyleStatementDetailsParagraphStyles']
            );

            $metaInfoCell->addText(
                $clusteredStatementMetaData,
                $styles['textStyleStatementDetails'],
                $styles['textStyleStatementDetailsParagraphStyles']
            );
        }
    }

    /**
     * Generates a String including the externalId of the incoming statement array.
     * This includes logic to set "Kopie von" in case of current statement
     * is a copy of a statement and placeholder statement information.
     *
     * @param bool $leadingComma - If true, the returned string will be leaded by a comma
     *
     * @deprecated use {@link getIdStringFromObject} instead
     */
    protected function getIdString(array $statement, $leadingComma = false): string
    {
        $idString = $this->getTranslator()->trans('id').': '.$this->createExternIdString($statement);

        return $leadingComma ? ', '.$idString : $idString;
    }

    /**
     * T10049
     * Centralisation of logic to generate string of externId.
     *
     * Includes "Kopie von" in case of current statement is a copy of a statement and placeholder statement information.
     */
    public function createExternIdStringFromObject(Statement $statement): string
    {
        $externIdString = $statement->getExternId();

        // add "copyof"
        if (null !== $statement->getParentId()
            && $statement->getOriginalId() != $statement->getParentId()) {
            $externIdString = $this->translator->trans('copyof').' '.$externIdString;
        }

        // add former externID in case of statement was moved from another procedure
        // was moved?
        $placeholderStatement = $statement->getPlaceholderStatement();
        if (null !== $statement->getFormerExternId()) {
            $formerExternId = $statement->getFormerExternId();
            $nameOfFormerProcedure = $statement->getMovedFromProcedureName();
            $externIdString .= $this->createFormerProcedureSuffix($formerExternId, $nameOfFormerProcedure);
        } elseif (null !== $placeholderStatement) {
            $formerExternId = $placeholderStatement->getExternId();
            $nameOfFormerProcedure = $placeholderStatement->getProcedure()->getName();
            $externIdString .= $this->createFormerProcedureSuffix($formerExternId, $nameOfFormerProcedure);
        }

        // if statement was moved into another procedure, this will usually be displayed in the textfield of the statement
        return $externIdString;
    }

    /**
     * T10049
     * Centralisation of logic to generate string of externId.
     *
     * Includes "Kopie von" in case of current statement is a copy of a statement and placeholder statement information.
     *
     * @param array $statementArray
     *
     * @deprecated use {@link AssessmentTableServiceOutput::createExternIdStringFromObject} instead
     */
    public function createExternIdString($statementArray): string
    {
        $externIdString = '';

        // add "copyof"
        if (isset($statementArray['originalId']) && isset($statementArray['parentId'])
            && $statementArray['originalId'] != $statementArray['parentId']
            && false === is_null($statementArray['parentId'])) {
            $externIdString .= $this->translator->trans('copyof').' ';
        }

        $externIdString .= $statementArray['externId'];

        // add former externID in case of statement was moved from another procedure

        // was moved?
        if (array_key_exists('formerExternId', $statementArray) && false === is_null($statementArray['formerExternId'])) {
            $externIdString .= ' ('.$this->translator->trans('formerExternId').': '.$statementArray['formerExternId'].' '.$this->translator->trans('from').' '.$statementArray['movedFromProcedureName'].')';
        } else {
            if (array_key_exists('placeholderStatement', $statementArray)
                && false === is_null($statementArray['placeholderStatement'])) {
                // dont know, if $statementArray['placeholderStatement'] is an object or array. -> handle both cases:
                if ($statementArray['placeholderStatement'] instanceof Statement) {
                    $formerExternId = $statementArray['placeholderStatement']->getExternId();
                    $nameOfFormerProcedure = $statementArray['placeholderStatement']->getProcedure()->getName();
                } else {
                    $formerExternId = $statementArray['placeholderStatement']['externId'];
                    $nameOfFormerProcedure = $statementArray['placeholderStatement']['procedure']['name'];
                }
                $externIdString .= ' ('.$this->translator->trans('formerExternId').': '.$formerExternId.' '.$this->translator->trans('from').' '.$nameOfFormerProcedure.')';
            }
        }

        // if statement was moved into another procedure, this will usually be displayed in the textfield of the statement
        return $externIdString;
    }

    private function createFormerProcedureSuffix($formerExternId, $nameOfFormerProcedure): string
    {
        return ' ('.$this->translator->trans('formerExternId').': '.$formerExternId.' '.$this->translator->trans('from').' '.$nameOfFormerProcedure.')';
    }

    /**
     * Add each fragments in $item['fragments'] as a row with the fragment text on the left side and the fragment recommendation
     * on the right side. $item is needed for the submitterData of the first fragment.
     *
     * @param array[] $item
     * @param int     $textCellWidth
     * @param int     $recommendationCellWidth
     * @param array   $styles
     * @param bool    $anonymous
     */
    protected function addFragmentRows(
        $item,
        Table $assessmentTable,
        $textCellWidth,
        $recommendationCellWidth,
        $styles,
        $anonymous): void
    {
        foreach ($item['fragments'] as $index => $fragment) {
            $assessmentTable->addRow();

            if (0 === $index) {
                // First Fragment
                $this->addSubmitterData($anonymous, $assessmentTable, $item, $styles);
            } else {
                $this->addSubmitterData($anonymous, $assessmentTable, $item, $styles, true);
            }

            $cell2 = $assessmentTable->addCell($textCellWidth, $styles['cellTop']);
            if (isset($fragment['text'])) {
                // T6679:
                $fragment['text'] = $this->editorService->handleObscureTags($fragment['text'], $anonymous);
                $this->addHtml($cell2, $fragment['text'], $styles);
            }

            $cell3 = $assessmentTable->addCell($recommendationCellWidth, $styles['cellTop']);
            if (isset($fragment['recommendation'])) {
                $fragment['recommendation'] = $this->editorService->handleObscureTags($fragment['recommendation'], $anonymous);
                $this->addHtml($cell3, $fragment['recommendation'], $styles);
            }
        }
    }

    /**
     * Used for non original statement and more?!
     * For OriginalStatements AssessmentTableServiceOutput::addHtml is used.
     *
     * @param Cell    $cell
     * @param string  $text
     * @param array[] $styles
     *
     * @return string
     */
    public function addHtml($cell, $text, $styles)
    {
        if ('' === $text) {
            return '';
        }
        try {
            $text = self::replaceTags($text);
            Html::addHtml($cell, $text, false);
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not parse HTML in Export', [$e, $text, $e->getTraceAsString()]);
            // fallback: print with html tags
            $cell->addText(
                $text,
                $styles['textStyleStatementDetails'],
                $styles['textStyleStatementDetailsParagraphStyles']
            );
        }

        return '';
    }

    private static function replaceTags(string $text): string
    {
        $replacements = [
            // phpword breaks when self closing tags are not closed
            // as only br as "void-elememt" is allowed use specific regex
            '/<(\s)*br(\s)*>/i'                 => '<br/>',
            // Handle strikethrough
            '/<s>/i'                            => '<span style="text-decoration: line-through">',
            '/<\/s>/i'                          => '</span>',
            // Handle mark
            '/<mark title="markierter Text">/i' => '<span style="background-color:#FFFF00">',
            '/<\/mark>/i'                       => '</span>',
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * @return Logger
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    protected function createFrontPage(PhpWord $phpWord, Procedure $procedure, ViewOrientation $orientation): Section
    {
        $translator = $this->translator;
        $styles = $this->getDefaultDocxPageStyles($orientation);
        $coverHeadingStyle = ['name' => 'Arial', 'size' => 18, 'bold' => true];
        $coverParagraphStyle = ['align' => 'center'];
        $coverStyle = ['name' => 'Arial', 'size' => 14];

        // format FrontPage:
        $frontPageSection = $phpWord->addSection($styles['orientation']);
        $frontPageSection->addText($translator->trans('synopsis'), $coverHeadingStyle, $coverParagraphStyle);
        $frontPageSection->addText($translator->trans('of.Statement.belonging.to.Procedure'), $coverHeadingStyle, $coverParagraphStyle);
        $frontPageSection->addTextBreak(3);

        // Verfahrensname
        $frontPageSection->addText(htmlspecialchars((string) $procedure->getName(), ENT_NOQUOTES), $coverHeadingStyle, $coverParagraphStyle);

        // Verfahrensschritt
        $phaseName = $procedure->getPhaseName();
        if (null !== $phaseName) {
            $frontPageSection->addText(htmlspecialchars((string) $phaseName), $coverHeadingStyle, $coverParagraphStyle);
        }

        $frontPageSection->addTextBreak(3);
        $frontPageSection->addText($translator->trans('date.created.noun').': '.date('d.m.Y H:i'), $coverStyle, $coverParagraphStyle);
        $frontPageSection->addText($translator->trans('procedure.agency').': '.$procedure->getOrgaName(), $coverStyle, $coverParagraphStyle);

        return $frontPageSection;
    }

    /**
     * @throws Exception
     */
    protected function renderGroup(
        StatementEntityGroup $group,
        callable $entriesRenderFunction,
        Section $section,
        int $depth = 0,
    ): void {
        $section->addTitle($group->getTitle(), $depth + 2);

        foreach ($group->getSubgroups() as $subgroup) {
            $this->renderGroup(
                $subgroup,
                $entriesRenderFunction,
                $section,
                $depth + 1
            );
        }

        if (0 !== (is_countable($group->getEntries()) ? count($group->getEntries()) : 0)) {
            $entriesRenderFunction($section, $group->getEntries());
        }
    }

    protected function getExportPageHeader(Procedure $procedure): string
    {
        return htmlspecialchars(
            $procedure->getOrgaName().' - '.$procedure->getName()
            .' - '.$this->translator->trans('as.at').': '.date('d.m.Y')
        );
    }

    /**
     * If $exportType is 'statementsAndFragments' use the fragments of a statement instead of the
     * statement if there are any (if not use the statement).
     * <p>
     * The fragments are converted in a special export format and sorted by their 'created' date property.
     *
     * @param array  $statements array of statements in their legacy array form
     * @param string $exportType used to determine if statements are replaced by their fragments
     *
     * @return Collection the new array with statements and fragments possibly mixed
     *
     * @throws Exception
     */
    protected function convertStatementsForExport(array $statements, $exportType, array $requestPost)
    {
        return collect($statements)
            ->map(function (array $statement) use ($exportType, $requestPost): array {
                $item = $this->formatStatementArray($statement);

                // if there are fragments and fragment export was selected
                if ('statementsAndFragments' === $exportType && 0 < (is_countable($statement['fragments']) ? count($statement['fragments']) : 0)) {
                    // change type of entry (used for name of column)
                    $item['type'] = 'fragments';
                    $item['fragments'] = collect($statement['fragments'])
                        ->filter(static fn (array $fragment): bool =>
                            // if some items are selected, then only export the selected ones
                            // if no items are selected, export all
                            0 === (is_countable($requestPost['items']) ? count($requestPost['items']) : 0)
                            || in_array($fragment['id'], $requestPost['items'], true)
                            || in_array($statement['id'], $requestPost['items'], true))
                        ->map(fn (array $fragment): array => $this->formatFragmentArray($statement, $fragment))
                        ->sortBy('sortIndex')
                        ->values();
                }

                return $item;
            });
    }

    /**
     * Fragment in unified data format.
     *
     * @return array - formatted fragment
     *
     * @throws Exception
     *
     * @deprecated Use {@link formatFragmentObject} instead
     */
    public function formatFragmentArray(array $statement, array $fragment): array
    {
        $tmpElementId = $fragment['elementId'];
        $tmpElementTitle = $fragment['elementTitle'];

        $item = $this->formatStatementArray($statement);
        $item['sortIndex'] = $fragment['sortIndex'];

        // override selected item fields with fragment content:
        $item['type'] = 'fragment';
        $item['created'] = $fragment['created'] ?? null;

        $item['text'] = '';
        $item['recommendation'] = '';
        // we need to fetch Fragment, as text fields are not mapped in statement
        // index for performance reasons
        $statementFragment = $this->statementHandler->getStatementFragment($fragment['id']);
        if ($statementFragment instanceof StatementFragment) {
            // pretend as if consideration would be an recommendation
            // as it has the same behaviour
            $item['recommendation'] = $statementFragment->getConsideration();
            $item['text'] = $statementFragment->getText();
        }

        $item['elementId'] = $tmpElementId;
        $item['elementTitle'] = $tmpElementTitle;

        return $item;
    }

    /**
     * Returns formatted label and content of the toeb address.
     *
     * @return array<string, string>
     */
    public function getCellsForOrganisation(Statement $statement, bool $anonym): array
    {
        $result = [];
        $meta = $statement->getMeta();

        if ('' != $meta->getOrgaName()) {
            $result['orgaName'] = $this->translator->trans('invitable_institution')
                .': '.$meta->getOrgaName();
        } else {
            $result['orgaName'] = $this->translator->trans('invitable_institution').': '.
                $this->translator->trans('notgiven');
        }
        if ('' != $meta->getOrgaDepartmentName()) {
            $result['orgaDepartment'] = $this->translator->trans('department')
                .': '.$meta->getOrgaDepartmentName();
        } else {
            $result['orgaDepartment'] = $this->translator->trans('department')
                .': '.$this->translator->trans('notgiven');
        }
        if ('' != $meta->getSubmitName()) {
            $result['submitName'] = $this->translator->trans('name')
                .': '.$meta->getSubmitName();
        } else {
            $result['submitName'] = $this->translator->trans('name')
                .': '.$this->translator->trans('notgiven');
        }

        if ('' !== $statement->getOrgaEmail()) {
            $result['email'] = $this->translator->trans('email.address').': '.$statement->getOrgaEmail();
        }
        if ('' !== $statement->getSubmitterPhoneNumber()) {
            $result['phoneNumber'] = $this->translator->trans('phone').': '.$statement->getSubmitterPhoneNumber();
        }

        $result['postalAddressPartsOfAuthor'] = $this->getFormattedAddressCell($statement, $anonym);

        return $result;
    }

    /**
     * Generates a String including the externalId of the incoming statement.
     * This includes logic to set "Kopie von" in case of current statement
     * is a copy of a statement and placeholder statement information.
     */
    protected function getIdStringFromObject(Statement $statement): string
    {
        return $this->getTranslator()->trans('id').': '.$this->createExternIdStringFromObject($statement);
    }

    /**
     * Docx-"Querformat"-Export of Entry of ATable.
     */
    protected function createStatementDocxEntry(
        Statement $statement,
        Table $assessmentTable,
        ViewOrientation $orientation,
        bool $anonym,
        string $templateName,
        int $statementNumber,
        bool $numberStatements,
    ): void {
        $statementAdviceValues = $this->config->getFormOptions()['statement_fragment_advice_values'];
        $styles = $this->getDefaultDocxPageStyles($orientation);

        $cellRowContinue = ['vMerge' => 'continue'];
        $rowStyleLocation = ['cantSplit' => true];
        $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'top', 'align' => 'center'];
        $cellStyleLocation = ['gridSpan' => 2, 'valign' => 'center'];
        $cellStyleStatementDetails = ['gridSpan' => 2, 'bgColor' => 'CACACA', 'valign' => 'center', 'borderBottomSize' => 0, 'borderBottomColor' => 'CACACA'];
        $cellTop = ['valign' => 'top'];
        $cellHCentered = ['valign' => 'center', 'spaceAfter' => 0];
        $statementNumberFontStyle = ['bold' => true];

        $assessmentTable->addRow(400);
        $cell1 = $assessmentTable->addCell($styles['firstCellWidth'], $cellRowSpan);
        $cell1AddText = $this->containerAddTextFunctionConstructor($cell1, null, $cellHCentered);
        $cell1AddStatementNumber = $this->containerAddTextFunctionConstructor(
            $cell1,
            $statementNumberFontStyle,
            $cellHCentered
        );
        $exportConfig = $statement->getProcedure()->getDefaultExportFieldsConfiguration();

        if ($numberStatements) {
            $cell1AddStatementNumber('statement.nr', $statementNumber);
        }

        if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ID, $exportConfig, $statement)) {
            $cell1->addText(htmlspecialchars($this->getIdStringFromObject($statement)), null, $cellHCentered);
        }

        if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_STATEMENT_NAME, $exportConfig, $statement)) {
            $cell1->addText('');
            $label = $statement->isClusterStatement() ? 'cluster.name' : 'name';
            $cell1AddText($label, $statement->getName(), ":\r");
        }

        if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_CREATION_DATE, $exportConfig, $statement)) {
            $cell1->addText('');
            // Einreichungsdatum
            $cell1AddText('submitted.date', date('d.m.Y', $statement->getSubmit()));
        }

        $cell2 = $assessmentTable->addCell($styles['cellWidthSecondThird'], $cellStyleStatementDetails);
        $cell2AddText = $this->containerAddTextFunctionConstructor($cell2, null, $cellHCentered);

        if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_MOVED_TO_PROCEDURE, $exportConfig, $statement)) {
            $cell2->addText(
                $this->translator->trans('statement.moved', ['name' => $statement->getMovedToProcedureName()]),
                null,
                $cellHCentered
            );
        } else {
            // Verfahrensname
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_PROCEDURE_NAME, $exportConfig, $statement)) {
                $cell2AddText('procedure.name', $statement->getProcedure()->getName());
            }
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_PROCEDURE_PHASE, $exportConfig, $statement)) {
                // Verfahrensschritt
                // Ersetze die Phase, in der die SN eingegangen ist
                $phaseName = $this->statementService->getPhaseName(
                    $statement->getPhase(),
                    $statement->getPublicStatement()
                );
                $cell2AddText('procedure.public.phase', $phaseName);
            }

            // EXTERNAL state
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_USER_STATE, $exportConfig, $statement)) {
                $cell2AddText('state', $statement->getMeta()->getUserState());
            }
            // EXTERNAL group
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_USER_GROUP, $exportConfig, $statement)) {
                $cell2AddText('organisation', $statement->getMeta()->getUserGroup());
            }
            // EXTERNAL organisation
            if ($this->exportFieldDecider->isExportable(
                FieldDecider::FIELD_USER_ORGANISATION,
                $exportConfig,
                $statement
            )) {
                $cell2AddText('organisation.name', $statement->getMeta()->getUserOrganisation());
            }
            // EXTERNAL position
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_USER_POSITION, $exportConfig, $statement)) {
                $cell2AddText('position', $statement->getMeta()->getUserPosition());
            }
            $permissions = $this->permissions;
            // SN von Töbs
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ORGA_INFO, $exportConfig, $statement)) {
                $organisationData = $this->getCellsForOrganisation($statement, $anonym);

                if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ORGA_NAME, $exportConfig, $statement)) {
                    $cell2->addText(
                        htmlspecialchars($organisationData['orgaName']),
                        null,
                        $cellHCentered
                    );
                }

                // Abteilung
                if ($this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_ORGA_DEPARTMENT,
                    $exportConfig,
                    $statement,
                    $organisationData
                )) {
                    $cell2->addText(
                        htmlspecialchars($organisationData['orgaDepartment']),
                        null,
                        $cellHCentered
                    );
                }

                // Address
                if ($this->isAddressExportable($organisationData, $exportConfig, $statement, $anonym)) {
                    $cell2->addText(
                        htmlspecialchars($organisationData['postalAddressPartsOfAuthor']),
                        null,
                        $cellHCentered
                    );
                }

                if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_SUBMITTER_NAME,
                    $exportConfig,
                    $statement,
                    $organisationData,
                    $anonym
                )) {
                    $cell2->addText(
                        htmlspecialchars($organisationData['submitName']),
                        null,
                        $cellHCentered
                    );
                }
                if ($this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_EMAIL,
                    $exportConfig,
                    $statement,
                    $organisationData,
                    $anonym
                )) {
                    $cell2->addText(
                        htmlspecialchars($organisationData['email']),
                        null,
                        $cellHCentered
                    );
                }
                if ($this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_PHONE_NUMBER,
                    $exportConfig,
                    $statement,
                    $organisationData,
                    $anonym
                )) {
                    $cell2->addText(
                        htmlspecialchars($organisationData['phoneNumber']),
                        null,
                        $cellHCentered
                    );
                }
            }
            // SN von Bürgern
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_CITIZEN_INFO, $exportConfig, $statement)) {
                $citizenDetails = $this->getCellsForCitizen($statement, $anonym);

                if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ORGA_NAME, $exportConfig, $statement)) {
                    $cell2->addText(
                        htmlspecialchars((string) $citizenDetails['orgaName']),
                        null,
                        $cellHCentered
                    );
                }

                if ($this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_SUBMITTER_NAME,
                    $exportConfig,
                    $statement,
                    $citizenDetails,
                    $anonym
                )) {
                    // Name
                    $cell2->addText(
                        htmlspecialchars((string) $citizenDetails['submitName']),
                        null,
                        $cellHCentered
                    );
                }

                if ($this->isAddressExportable($citizenDetails, $exportConfig, $statement, $anonym)) {
                    // Adresse
                    $cell2->addText(
                        htmlspecialchars((string) $citizenDetails['postalAddressPartsOfAuthor']),
                        null,
                        $cellHCentered
                    );
                }

                if ($this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_EMAIL,
                    $exportConfig,
                    $statement,
                    $citizenDetails,
                    $anonym
                )) {
                    $cell2->addText(
                        htmlspecialchars((string) $citizenDetails['email']),
                        null,
                        $cellHCentered
                    );
                }
                if ($this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_PHONE_NUMBER,
                    $exportConfig,
                    $statement,
                    $citizenDetails,
                    $anonym
                )) {
                    // Adresse
                    $cell2->addText(
                        htmlspecialchars((string) $citizenDetails['phoneNumber']),
                        null,
                        $cellHCentered
                    );
                }
            }

            if ($this->exportFieldDecider->isExportable(
                FieldDecider::FIELD_SHOW_IN_PUBLIC_AREA,
                $exportConfig,
                $statement,
                []
            )) {
                // Veröffentlichen
                $cell2->addText(
                    $this->translator->trans('publish.on.platform').': '.
                    $this->translator->trans($statement->getPublicVerifiedTranslation())
                );
            }

            // Mitzeichnungen
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_VOTES_NUM, $exportConfig, $statement)) {
                $text = ' - ';
                $text .= $this->translator->trans('voters')
                    .': '.$statement->getVotesNum().' ';
                if (1 == $statement->getVotesNum()) {
                    $text .= $this->translator->trans('person');
                } else {
                    $text .= $this->translator->trans('persons');
                }
                $cell2->addText($text);
            }
            // Planungsdokument
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_DOCUMENT, $exportConfig, $statement)) {
                $textrun1 = $cell2->addTextRun($cellHCentered);
                $textRun1AddText = $this->containerAddTextFunctionConstructor($textrun1, null, null);
                $textRun1AddText('document', $statement->getElement()->getTitle());
                if (null !== $statement->getDocument()) {
                    $textrun1->addText(
                        htmlspecialchars(
                            ' / '.$statement->getDocument()->getTitle()
                        )
                    );
                }
            }
            // Kapitel
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_PARAGRAPH, $exportConfig, $statement)) {
                $cell2AddText('paragraph', $statement->getParagraph()->getTitle());
            }
            // Dateien
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_FILES, $exportConfig, $statement)) {
                foreach ($statement->getFiles() as $fileString) {
                    if ($fileString instanceof File) {
                        $fileName = $fileString->getFilename();
                    } else {
                        $file = explode(':', (string) $fileString);
                        $fileName = $file[0] ?? '';
                    }
                    if ($anonym) {
                        $cell2AddText('file', $this->getTranslator()->trans('files.attached'));
                    } else {
                        $cell2AddText('file', $fileName);
                    }
                }
            }
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ATTACHMENTS, $exportConfig, $statement)) {
                // Source statement
                collect($statement->getAttachments())
                    ->filter(static fn (StatementAttachment $attachment): bool => StatementAttachment::SOURCE_STATEMENT === $attachment->getType())->each(function (StatementAttachment $attachment) use ($cell2AddText, $anonym) {
                        $displayValue = $anonym
                            ? $this->getTranslator()->trans('file.attached')
                            : $attachment->getFile()->getName();
                        $cell2AddText('attachment.original', $displayValue);
                    });
            }

            // Priorität
            if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_PRIORITY, $exportConfig, $statement)) {
                $textRun2 = $cell2->addTextRun($cellHCentered);
                $textRun2AddText = $this->containerAddTextFunctionConstructor($textRun2, null, null);
                $textRun2AddText('priority', '');
                $textRun2->addText(htmlspecialchars((string) $statement->getPriority()));
            }

            // TODO: implement a configuration system to set which templates should export which data to remove the
            // hardcoded template names here
            if (0 < $statement->getFragments()->count() && ('landscapeWithFrags' === $templateName || 'portraitWithFrags' === $templateName)) {
                $assessmentTable->addRow(100);
                $assessmentTable->addCell(null, $cellRowContinue);
                $headerCell = $assessmentTable->addCell($styles['cellWidth'], $cellTop);
                Html::addHtml($headerCell, $this->translator->trans('fragment.specification'));
                $headerCell = $assessmentTable->addCell($styles['cellWidth'], $cellTop);
                Html::addHtml($headerCell, $this->translator->trans('considerationadvice'));
                // sort statementFragments as it is not so easy to sort them via elasticsearch. Might be improved
                // has no significant performance effect in symfony profiler
                $fragments = collect($statement->getFragments())->sortByDesc('created')->values()->all();
                foreach ($fragments as $fragment) {
                    $assessmentTable->addRow(400);
                    $assessmentTable->addCell(null, $cellRowContinue);
                    $cell2 = $assessmentTable->addCell($styles['cellWidth'], $cellTop);
                    if (isset($fragment['text'])) {
                        // T6679:
                        $fragment['text'] = $this->editorService->handleObscureTags($fragment['text'], $anonym);
                        $this->addHtml($cell2, $fragment['text'], $styles);
                    }
                    $cell3 = $assessmentTable->addCell($styles['cellWidth'], $cellTop);
                    if (isset($fragment['consideration'])) {
                        $fragment['consideration'] = $this->editorService->handleObscureTags($fragment['consideration'], $anonym);
                        $this->addHtml($cell3, $fragment['consideration'], $styles);
                    }
                }
            } else {
                // Stellungnahme und Abwägung
                $assessmentTable->addRow(400);
                $assessmentTable->addCell(null, $cellRowContinue);
                $cell2 = $assessmentTable->addCell($styles['cellWidth'], $cellTop);
                // T6679:
                $statementText = $this->editorService->handleObscureTags($statement->getText(), $anonym);
                $this->addHtml($cell2, $statementText, $styles);
                $cell3 = $assessmentTable->addCell($styles['cellWidth'], $cellTop);
                if (true === $permissions->hasPermission(
                    'field_vote_advice_docx'
                ) && null !== $statement->getVotePla()) {
                    try {
                        $votePla = $statement->getVotePla();
                        $translationKey = $statementAdviceValues[$votePla];
                        $voteTextShort = $this->translator->trans($translationKey);
                        Html::addHtml($cell3, '<p><strong>'.$voteTextShort.'</strong></p><br />');
                    } catch (Exception $e) {
                        // log in case of invalid $votePla values (will not be found in the $statementAdviceValues array, hence the ContextErrorException exception)
                        $this->getLogger()->warning('statement with invalid \'votePla\' value given to export', [$e]);
                    }
                }
                // no obscured texts in recommendation, so no need to handle obscure tags
                $this->addHtml($cell3, $statement->getRecommendation(), $styles);
            }
            // Verortung-Screenshot
            $this->addLocationScreenshotIfPresent(
                $statement,
                $rowStyleLocation,
                $cellRowContinue,
                $assessmentTable,
                $styles,
                $cellStyleLocation,
                $cellHCentered);
        }
    }

    /**
     * Gibt die Label udn den  Inhalt für die Details zum Bürger aus.
     */
    public function getCellsForCitizen(Statement $statement, bool $anonym): array
    {
        $permissions = $this->permissions;
        $result = [];
        $meta = $statement->getMeta();

        if ('' != $meta->getOrgaName()) {
            $result['orgaName'] = $this->translator->trans('submitted.author')
                .': '.$meta->getOrgaName();
        }
        if ('' != $meta->getSubmitName()) {
            $result['submitName'] = $this->translator->trans('name')
                .': '.$meta->getSubmitName();
        } elseif ('' != $meta->getAuthorName()) {
            $result['submitName'] = $this->translator->trans('name')
                .': '.$meta->getAuthorName();
        } else {
            $result['submitName'] = $this->translator->trans('name')
                .': '.$this->translator->trans('anonymous');
        }

        // T14612 / T15489:
        $houseNumber = '';
        if ($permissions->hasPermission('feature_statement_meta_house_number_export')) {
            $houseNumber = ' '.$meta->getHouseNumber();
        }

        $result['postalAddressPartsOfAuthor'] = $this->getFormattedAddressCell($statement, $anonym);

        // in Hamburg wird nur anonyme Ansicht verwendet, beim Bürger soll allerdings die Straße mit angegeben werden
        if ($this->permissions->hasPermission('feature_keep_street_on_anonymize')) {
            if (0 < strlen((string) $statement->getMeta()->getOrgaStreet())) {
                $result['orgaName'] .= ', '.$statement->getMeta()->getOrgaStreet().$houseNumber;
            }
            // @improve this special cases should not be mixed with this permission check
            // instead, single permissions should be used
            // die Darstellung mit Namen wird in Hamburg  nicht abgerufen, deshalb werden Variablen nicht gefüllt
            $result['submitName'] = '';
            $result['address'] = '';
            $result['votes'] = '';
        }

        if ('' !== $statement->getOrgaEmail()) {
            $result['email'] = $this->translator->trans('email.address').': '.$statement->getOrgaEmail();
        }
        if ('' !== $statement->getSubmitterPhoneNumber()) {
            $result['phoneNumber'] = $this->translator->trans('phone').': '.$statement->getSubmitterPhoneNumber();
        }

        return $result;
    }

    // T23913:
    /**
     * Format the given address parts, depending on existence, permission and selection of option to render.
     */
    private function getFormattedAddressCell(Statement $statement, bool $anonym): string
    {
        $exportConfig = $statement->getProcedure()->getDefaultExportFieldsConfiguration();
        $meta = $statement->getMeta();

        $street = '';
        if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ADDRESS, $exportConfig, $statement, [], $anonym)
            && '' !== $meta->getOrgaStreet()
        ) {
            $street = $meta->getOrgaStreet();
        }

        // T14612 / T15489:
        $houseNumber = '';
        if ($this->permissions->hasPermission('feature_statement_meta_house_number_export')
            && $this->exportFieldDecider->isExportable(FieldDecider::FIELD_ADDRESS_HOUSENUMBER, $exportConfig, $statement, [], $anonym)
            && '' !== $meta->getHouseNumber()
        ) {
            $houseNumber = $meta->getHouseNumber();
        }

        $postalCode = '';
        if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ADDRESS_POSTALCODE, $exportConfig, $statement, [], $anonym)
            && '' !== $meta->getOrgaPostalCode()
        ) {
            $postalCode = $meta->getOrgaPostalCode();
        }

        $city = '';
        if ($this->exportFieldDecider->isExportable(FieldDecider::FIELD_ADDRESS_CITY, $exportConfig, $statement, [], $anonym)
            && '' !== $meta->getOrgaCity()
        ) {
            $city = $meta->getOrgaCity();
        }

        if ('' === $street && '' === $postalCode && '' === $city) {
            return $this->translator->trans('address').': '.$this->translator->trans('notgiven');
        }

        // Depending on filled variables, trim will handle formatting-logic of comma and spaces.
        $formattedStreetAndHouseNumber = trim($street.' '.$houseNumber);
        $formattedPostalCodeAndCity = trim($postalCode.' '.$city);
        $formattedPostalAddress = trim($formattedStreetAndHouseNumber.', '.$formattedPostalCodeAndCity, ' ,');

        return $this->translator->trans('address').': '.$formattedPostalAddress;
    }

    /**
     * @param array $rowStyleLocation
     * @param array $cellRowContinue
     * @param Table $assessmentTable
     * @param array $styles
     * @param array $cellStyleLocation
     * @param array $cellHCentered
     */
    protected function addLocationScreenshotIfPresent(
        Statement $statement,
        $rowStyleLocation,
        $cellRowContinue,
        $assessmentTable,
        $styles,
        $cellStyleLocation,
        $cellHCentered,
    ) {
        $fileAbsolutePath = $this->getScreenshot($statement->getMapFile() ?? '');
        if (null !== $fileAbsolutePath) {
            $assessmentTable->addRow(400, $rowStyleLocation);
            $assessmentTable->addCell(null, $cellRowContinue);
            $cell2 = $assessmentTable->addCell(
                $styles['cellWidthSecondThird'],
                $cellStyleLocation
            );
            $cell2->addText(
                htmlspecialchars($this->translator->trans('location')),
                null,
                $cellHCentered
            );
            if (file_exists($fileAbsolutePath)) {
                // use Html::addHtml() because $cell2->addImage() ignored sizes
                Html::addHtml($cell2, $this->getDocxImageTag($fileAbsolutePath));
            }
            $cell2->addText($this->mapService->getReplacedMapAttribution($statement->getProcedure()));
        }
    }

    /**
     * Generate Html imagetag to be used in PhpWord Html::addHtml().
     *
     * @param string $imageFile
     * @param int    $maxWidth  maximum image width in pixel
     *
     * @return string
     */
    protected function getDocxImageTag($imageFile, $maxWidth = 500)
    {
        $imgTag = '';
        $width = 300;
        $height = 300;
        $margin = 10;
        if (!file_exists($imageFile)) {
            return $imgTag;
        }

        // get Image size
        $imageInfo = getimagesize($imageFile);
        if (2 < (is_countable($imageInfo) ? count($imageInfo) : 0)) {
            $width = $imageInfo[0] - $margin;
            $height = $imageInfo[1] - $margin;
        }

        // check that picture is not wider than allowed
        if ($width > $maxWidth) {
            $factor = $width / $maxWidth;

            // resize Image
            if (0 != $factor) {
                $width = $width / $factor;
                $height = $height / $factor;
            }
            $this->getLogger()->info('Docx Image resize to width: '.$width.' and height: '.$height);
        }

        return '<img height="'.$height.'" width="'.$width.'" src="'.$imageFile.'"/>';
    }

    /**
     * Returns the absolute path to the screenshot of the given map file string.
     */
    public function getScreenshot(string $mapFile): ?string
    {
        if ('' !== $mapFile && Statement::MAP_FILE_EMPTY_DASHED !== $mapFile) {
            // Hole den Screenshot-Hash
            $parts = explode(':', $mapFile);
            $hash = $parts[1] ?? '';
            // Lege den Screenshot in den Tmp-Ordner
            try {
                $file = $this->fileService->getFileInfo($hash);
            } catch (Exception) {
                $this->getLogger()->warning('Could not find file for hash');

                return '';
            }

            return $file->getAbsolutePath();
        }

        return null;
    }

    /**
     * Creates and returns a new function that uses the given parameter values on every of its invocations without the
     * necessity to pass them every time when the returned function is called. The returned function itself takes two
     * parameter values: $transKey and $concatValue (both strings).
     *
     * When the returned function is invoked it adds text to the defined $cell along with the styles $fStyle and
     * $pStyle. The first string ($transKey) is translated using the translator of this class and
     * concatenated with the $delimiter and again concatenated with the second string ($concatValue). Special
     * characters in the result of this concatenation are converted to HTML entities before the result of this
     * conversion is added as the text mentioned at the beginning.
     *
     * @param AbstractContainer $cell
     * @param array             $fStyle
     * @param array             $pStyle
     *
     * @return callable(string, string, string=): void
     */
    protected function containerAddTextFunctionConstructor($cell, $fStyle, $pStyle): callable
    {
        // return a function that can take two parameters ($transKey and $concatValue) but uses
        // the parameters given when creating the function as well.
        /*
         * @param string string $transKey
         * @param string $concatValue
         * @param string $delimiter
         */
        return function (string $transKey, $concatValue, string $delimiter = ': ') use ($cell, $fStyle, $pStyle) {
            $cell->addText(
                htmlspecialchars(
                    $this->getTranslator()->trans($transKey)
                    .$delimiter.$concatValue,
                    ENT_NOQUOTES
                ),
                $fStyle,
                $pStyle);
        };
    }

    /**
     * @throws Exception
     */
    protected function renderStatementsInGroup(
        StatementEntityGroup $groupStructure,
        Section $section,
        array $styles,
        ViewOrientation $viewOrientation,
        bool $anonym,
        bool $numberStatements,
        string $templateName,
        int $depth = 0,
    ) {
        foreach ($groupStructure->getSubgroups() as $subgroup) {
            // show subgroup title only if it has any entries
            if (0 < $subgroup->getTotal()) {
                $section->addTitle($subgroup->getTitle(), $depth + 2);
                $this->renderStatementsInGroup(
                    $subgroup,
                    $section,
                    $styles,
                    $viewOrientation,
                    $anonym,
                    $numberStatements,
                    $templateName,
                    $depth + 1
                );
            }
        }
        $statementNumber = 1;
        foreach ($groupStructure->getEntries() as $entry) {
            $assessmentTable = $section->addTable('assessmentTable');
            $assessmentTable->addRow(100, $this->firstRowStyle);

            $assessmentTable->addCell($styles['firstCellWidth'], $this->cellHCentered)
                ->addText('');

            $assessmentTable->addCell($styles['cellWidth'], $this->cellHCentered)
                ->addText(htmlspecialchars($this->translator->trans('statement.specification')));

            $assessmentTable->addCell($styles['cellWidth'], $this->cellHCentered)
                ->addText(htmlspecialchars($this->translator->trans('considerationadvice')));
            $this->createStatementDocxEntry(
                $entry,
                $assessmentTable,
                $viewOrientation,
                $anonym,
                $templateName,
                $statementNumber,
                $numberStatements
            );
        }
        // add an empty line after the table containing statements
        if (0 !== (is_countable($groupStructure->getEntries()) ? count($groupStructure->getEntries()) : 0)) {
            $section->addTextBreak();
        }
    }

    /**
     * @param Procedure $procedure
     *
     * @throws \PhpOffice\PhpWord\Exception\Exception
     * @throws Exception
     */
    public function createDocxGrouped(
        $procedure,
        StatementEntityGroup $groupStructure,
        ViewOrientation $orientation,
        PhpWord $phpWord,
        callable $entriesRenderFunction,
    ): WriterInterface {
        $phpWord->setDefaultFontSize(9);
        $phpWord->addTitleStyle(2, ['size' => 16, 'color' => '666666']);
        $styles = $this->getDefaultDocxPageStyles($orientation);
        $this->createFrontPage($phpWord, $procedure, $orientation);

        $phpWord->addTitleStyle(2, ['size' => 18, 'color' => '000000'], ['spaceAfter' => 160, 'spaceBefore' => 240]);
        $phpWord->addTitleStyle(3, ['size' => 14, 'color' => '666666'], ['spaceAfter' => 160, 'spaceBefore' => 240]);

        foreach ($groupStructure->getSubgroups() as $group) {
            $section = $phpWord->addSection($styles['orientation']);
            $header = $section->addHeader();
            $header->addText($this->getExportPageHeader($procedure));
            $footer = $section->addFooter();
            $footer->addPreserveText('{PAGE}/{NUMPAGES}');
            $this->renderGroup(
                $group,
                $entriesRenderFunction,
                $section
            );
        }

        return IOFactory::createWriter($phpWord, 'Word2007');
    }

    /**
     * Creates a condensed docx document as used in robob.
     *
     * @param array[] $statements
     * @param bool    $anonymous
     * @param string  $exportType
     *
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    protected function createDocxCondensed(
        Procedure $procedure,
        array $statements,
        $anonymous,
        bool $numberStatements,
        ViewOrientation $orientation,
        PhpWord $phpWord,
        $exportType,
        array $requestPost,
    ): WriterInterface {
        $phpWord->setDefaultFontSize(9);
        $styles = $this->getDefaultDocxPageStyles($orientation);

        $this->createFrontPage($phpWord, $procedure, $orientation);
        $items = $this->convertStatementsForExport($statements, $exportType, $requestPost);
        $typeHeader = $this->translator->trans('fragment');
        if ('statementsOnly' === $exportType) {
            $typeHeader = $this->translator->trans('statement');
        }

        // format Table:
        $tableSection = $phpWord->addSection($styles['orientation']);

        // Header
        $header = $tableSection->addHeader();
        $header->addText($this->getExportPageHeader($procedure));
        $assessmentTable = $tableSection->addTable($styles['tableStyle']);

        // Adds headers to every page of table
        $this->addCondensedTableHeaders($styles, $assessmentTable, $typeHeader);

        $statementNumber = 1;
        foreach ($items->toArray() as $item) {
            $this->renderTableItem(
                $assessmentTable,
                $item,
                $anonymous,
                $orientation,
                $exportType,
                $numberStatements,
                $statementNumber
            );
            ++$statementNumber;
        }

        $footer = $tableSection->addFooter();
        $footer->addPreserveText('{PAGE}/{NUMPAGES}');

        return IOFactory::createWriter($phpWord, 'Word2007');
    }

    /**
     * Creates a docx document as used in bobsh
     * Unstructured landscape docx-export of statements (+ anonym).
     *
     * @param array<string, Statement> $statements
     *
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    protected function createDocxUngrouped(
        array $statements,
        bool $anonym,
        bool $numberStatements,
        string $templateName,
        PhpWord $phpWord,
        ViewOrientation $viewOrientation,
        array $styles,
        Section $section,
    ): WriterInterface {
        $assessmentTable = $section->addTable('assessmentTable');

        $statementNumber = 1;
        foreach ($statements as $statement) {
            $assessmentTable->addRow(100, $this->firstRowStyle);

            $assessmentTable->addCell($styles['firstCellWidth'], $this->cellHCentered)
                ->addText('');

            $assessmentTable->addCell($styles['cellWidth'], $this->cellHCentered)
                ->addText(htmlspecialchars($this->translator->trans('statement.specification')));

            $assessmentTable->addCell($styles['cellWidth'], $this->cellHCentered)
                ->addText(htmlspecialchars($this->translator->trans('considerationadvice')));
            $this->createStatementDocxEntry(
                $statement,
                $assessmentTable,
                $viewOrientation,
                $anonym,
                $templateName,
                $statementNumber,
                $numberStatements
            );
            ++$statementNumber;
        }

        $footer = $section->addFooter();
        $footer->addPreserveText('{PAGE}/{NUMPAGES}');

        return IOFactory::createWriter($phpWord, 'Word2007');
    }

    /**
     * This export was originally developed for the BobHH project.
     * If it becomes more common accross projects it can be renamed to distinguish it from other
     * exports doing similar but nonetheless different things. Ideally the method name
     * would reflect what it does instead for whom.
     *
     * @throws \PhpOffice\PhpWord\Exception\Exception
     * @throws Exception
     */
    protected function createDocxGroupedBobHH(
        StatementEntityGroup $groupStructure,
        bool $anonym,
        bool $numberStatements,
        string $templateName,
        PhpWord $phpWord,
        ViewOrientation $viewOrientation,
        array $styles,
        Section $section,
    ): WriterInterface {
        $section->addTextBreak(2);
        $section->addText($this->translator->trans('summary.colon'));
        $section->addTOC();
        $section->addPageBreak();

        $this->renderStatementsInGroup(
            $groupStructure,
            $section,
            $styles,
            $viewOrientation,
            $anonym,
            $numberStatements,
            $templateName
        );

        $footer = $section->addFooter();
        $footer->addPreserveText('{PAGE}/{NUMPAGES}');

        return IOFactory::createWriter($phpWord, 'Word2007');
    }

    /**
     * In case of (parts of) address should be exported, but no address data is given (on this statement),
     * something like "address: not given" should be rendered.
     * exported.
     */
    private function isAddressExportable(
        array $data,
        ExportFieldsConfiguration $exportConfig,
        Statement $statement,
        bool $anonym,
    ): bool {
        return
            array_key_exists('postalAddressPartsOfAuthor', $data)
            && '' !== $data['postalAddressPartsOfAuthor']
            && (
                $this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_ADDRESS,
                    $exportConfig,
                    $statement,
                    $data,
                    $anonym
                )
                || $this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_ADDRESS_POSTALCODE,
                    $exportConfig,
                    $statement,
                    $data,
                    $anonym
                )
                || $this->exportFieldDecider->isExportable(
                    FieldDecider::FIELD_ADDRESS_CITY,
                    $exportConfig,
                    $statement,
                    $data,
                    $anonym
                )
            )
        ;
    }
}
