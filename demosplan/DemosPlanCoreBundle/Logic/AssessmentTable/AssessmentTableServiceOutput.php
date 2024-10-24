<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use Closure;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocxExporter;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Twig\Extension\PageTitleExtension;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\StatementHandlingResult;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\PresentableOriginalStatement;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\ValuedLabel;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Exception;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function collect;
use function date;
use function explode;
use function htmlspecialchars;
use function is_array;
use function is_string;
use function strlen;
use function strtotime;
use function substr;

class AssessmentTableServiceOutput
{
    final public const EXPORT_SORT_BY_PARAGRAPH_FRAGMENTS_ONLY = 'byParagraphFragmentsOnly';
    final public const EXPORT_SORT_BY_PARAGRAPH = 'byParagraph';
    final public const EXPORT_SORT_DEFAULT = 'default';

    /**
     * @var StatementService
     */
    protected $statementService;

    /**
     * @var FileService
     */
    protected $serviceFiles;

    /**
     * @var MapService
     */
    protected $serviceMap;

    /**
     * @var ServiceImporter
     */
    protected $serviceImport;

    /**
     * @var Environment
     */
    protected $twig;

    protected $logger;

    /** @var AssessmentTableServiceStorage */
    protected $assessmentTableServiceStorage;

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

    /**
     * Construktor Ausgabefunktionsklasse.
     */
    public function __construct(
        AssessmentTableServiceStorage $assessmentTableServiceStorage,
        private readonly CurrentUserService $currentUser,
        private readonly DocxExporter $docxExporter,
        Environment $twig,
        FileService $serviceFiles,
        FormFactoryInterface $formFactory,
        GlobalConfigInterface $config,
        MapService $serviceMap,
        LoggerInterface $logger,
        private readonly PageTitleExtension $pageTitleExtension,
        private readonly ParagraphService $paragraphService,
        ProcedureHandler $procedureHandler,
        PermissionsInterface $permissions,
        ServiceImporter $serviceImport,
        private readonly StatementHandler $statementHandler,
        StatementService $statementService,
        private readonly TranslatorInterface $translator,
        ValidatorInterface $validator,
    ) {
        $this->assessmentTableServiceStorage = $assessmentTableServiceStorage;
        $this->config = $config;
        $this->formFactory = $formFactory;
        $this->logger = $logger;
        $this->permissions = $permissions;
        $this->procedureHandler = $procedureHandler;
        $this->serviceFiles = $serviceFiles;
        $this->serviceImport = $serviceImport;
        $this->serviceMap = $serviceMap;
        $this->statementService = $statementService;
        $this->twig = $twig;
        $this->validator = $validator;
    }

    /**
     * * Liste der Stellungnahmen.
     *
     * @param string $procedureId
     * @param array  $rParams
     * @param bool   $aggregationsOnly
     * @param int    $aggregationsMinDocumentCount
     * @param bool   $addAllAggregations
     *
     * @throws MessageBagException
     */
    public function getStatementListHandler(
        $procedureId,
        $rParams,
        $aggregationsOnly = false,
        $aggregationsMinDocumentCount = 1,
        $addAllAggregations = true,
    ): StatementHandlingResult {
        $orgaId = $this->currentUser->getUser()->getOrganisationId();

        if (!$this->isOrgaAuthorized($procedureId, $orgaId)) {
            throw new AccessDeniedGuestException('Access Denied.');
        }

        if (!array_key_exists('search', $rParams)) {
            $rParams['search'] = null;
        }

        if (!array_key_exists('sort', $rParams)) {
            $rParams['sort'] = ToBy::createArray('submitDate', 'desc');
        }
        if (!array_key_exists('request', $rParams) || !array_key_exists('limit', $rParams['request'])) {
            $rParams['request']['limit'] = 0;
        }
        if (!array_key_exists('page', $rParams)) {
            $rParams['page'] = 1;
        }

        if (!array_key_exists('filters', $rParams) || !is_array($rParams['filters'])) {
            $rParams['filters'] = [];
        }

        if (!array_key_exists('searchFields', $rParams) || !is_array($rParams['searchFields'])) {
            $rParams['searchFields'] = [];
        }

        $this->assessmentTableServiceStorage->executeAdditionalTableAction($rParams);
        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $serviceResult = $this->statementService->getStatementsByProcedureId(
            $procedure->getId(),
            $rParams['filters'],
            $rParams['sort'],
            $rParams['search'],
            $rParams['request']['limit'],
            $rParams['page'],
            $rParams['searchFields'],
            $aggregationsOnly,
            $aggregationsMinDocumentCount,
            true,
            $addAllAggregations
        );

        $statements = array_map($this->replaceDataOfEsStatementFields(...), $serviceResult->getResult());

        $filterStatementFragments = false;
        if (!(1 === count($rParams['filters']) && isset($rParams['filters']['original']))
            || null !== $rParams['search']) {
            $filterStatementFragments = true;
        }

        $statements = $this->copyFragmentsOverToTotalField($statements);
        if ($filterStatementFragments && $this->permissions->hasPermission('feature_statements_fragment_add')) {
            $statements = $this->adjustStructureToFiltering($statements, $rParams);
        }

        return StatementHandlingResult::create(
            $this->procedureHandler->getProcedure($procedureId, false),
            $serviceResult->getSearch(),
            $serviceResult->getFilterSet(),
            $serviceResult->getSortingSet(),
            $serviceResult->getSearchFields(),
            $serviceResult->getTotal(),
            $statements,
            $serviceResult->getPager(),
            null
        );
    }

    /**
     * @param array $statements
     * @param array $params
     *
     * @return array
     *
     * @throws Exception
     */
    protected function adjustStructureToFiltering($statements, $params)
    {
        $statementIds = array_column($statements, 'id');

        // get fragments matching to current filter and statements
        $filteredFragments = $this->statementHandler->getStatementFragmentsStatementES(
            $statementIds,
            $this->statementService->mapRequestFiltersToESFragmentFilters($params['filters']),
            $params['search']
        );
        $filteredFragments = $filteredFragments->getResult();

        $result = [];
        foreach ($statements as $statement) {
            $statementFragments = array_filter(
                $filteredFragments,
                fn ($filteredFragment) => $filteredFragment['statementId'] === $statement['id']
            );
            if ((is_countable($statement['fragments']) ? count($statement['fragments']) : 0) !== count($statementFragments)) {
                $statement['fragments'] = $statementFragments;
            }
            $filteredFragments = array_diff_key($filteredFragments, $statementFragments);
            $result[] = $statement;
        }

        return $result;
    }

    /**
     * @param array[] $statements
     *
     * @return array[]
     */
    protected function copyFragmentsOverToTotalField($statements)
    {
        // always send the (unfiltered) statement fragments for a statement even if no filtering was done,
        // this is needed now because the total number of fragments for each statement is always needed
        // regardless of filtering
        foreach ($statements as $statementKey => $statement) {
            $statements[$statementKey]['fragments_total'] = $statement['fragments'];
        }

        return $statements;
    }

    /**
     * Get sorted form values from a request.
     *
     * @param array $rParams
     */
    public function getFormValues($rParams): array
    {
        $resParams = [
            'filters' => $this->statementService->collectFilters($rParams),
            'request' => $this->statementService->collectRequest($rParams),
            'items'   => $this->statementService->collectItems($rParams),
        ];
        $resParams = $this->statementService->maybeAddSort($rParams, $resParams);

        foreach ($rParams as $key => $value) {
            if (('' !== $value) && 'Suchbegriff eingeben' !== $value
                && str_contains($key, 'search_')) {
                $resParams['search'] = $value;
            }
        }

        return $resParams;
    }

    /**
     * @param PresentableOriginalStatement[] $presentableOriginalStatements
     */
    public function buildOriginalStatementDocxExport(Procedure $procedure, array $presentableOriginalStatements): PhpWord
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $phpWord->setDefaultFontSize(9);

        $section = $phpWord->addSection();
        $header = $section->addHeader();
        $header->addText($this->getExportPageHeader($procedure).', '.date('H:i'));
        $footer = $section->addFooter();
        $footer->addPreserveText(htmlspecialchars('{PAGE}'), null, ['align' => Jc::CENTER]);

        $phpWord->addTitleStyle(2, ['size' => 14, 'color' => '000000']);
        $title = $this->pageTitleExtension->pageTitle('statements.original');
        $section->addTitle($title, 2);

        $tableStyle = $this->getDefaultDocxTableStyle();
        $tableStyle->setCellMarginTop(20);
        $tableStyle->setCellMarginBottom(20);
        $boldFontSetting = ['bold' => true];

        foreach ($presentableOriginalStatements as $presentableOriginalStatement) {
            $section->addTextBreak(3);
            $table = $section->addTable($tableStyle);
            $internId = $presentableOriginalStatement->getInternId();
            if (null !== $internId) {
                $this->addRow($table, $internId);
            }
            $this->addRow($table, $presentableOriginalStatement->getExternId(), $boldFontSetting);
            $this->addRow($table, $presentableOriginalStatement->getSubmitDate());
            $this->addRow($table, $presentableOriginalStatement->getProcedurePublicPhase());
            $this->addRow($table, $presentableOriginalStatement->getSubmitterPublicAgency(), $boldFontSetting);
            $this->addRow($table, $presentableOriginalStatement->getSubmitterName());

            foreach ($presentableOriginalStatement->getOptionals() as $optional) {
                $this->addRow($table, $optional);
            }

            $section->addTextBreak();

            $movedToProcedureName = $presentableOriginalStatement->getMovedToProcedureName();
            if (null !== $movedToProcedureName) {
                $section->addText($this->translator->trans('statement.moved', ['name' => $movedToProcedureName]));
            } else {
                $section->addText($this->translator->trans('statement'), $boldFontSetting, ['align' => Jc::CENTER]);
                $section->addTextBreak();
                $this->docxExporter->addHtml($section, $presentableOriginalStatement->getStatementText(), []);

                if ($this->permissions->hasPermission('feature_statement_gdpr_consent')) {
                    if ($presentableOriginalStatement->getGdprConsentRevoked()) {
                        $section->addTextBreak();
                        $revokeNotice = $this->translator->trans('personal.data.usage.revoked');
                        $undeletedNotice = $this->translator->trans('personal.data.usage.revoked.statement');
                        $section->addText($revokeNotice.' '.$undeletedNotice);
                    } elseif ($presentableOriginalStatement->getGdprConsentReceived()) {
                        $section->addTextBreak();
                        $receivedNotice = $this->translator->trans('personal.data.usage.allowed');
                        $section->addText($receivedNotice);
                    }
                }

                if ($presentableOriginalStatement->getSubmitterAndAuthorMetaDataAnonymized()) {
                    $section->addTextBreak();
                    $section->addText($this->translator->trans('statement.anonymized.submitter.data'));
                }
                if ($presentableOriginalStatement->getTextPassagesAnonymized()) {
                    $section->addTextBreak();
                    $section->addText($this->translator->trans('statement.anonymized.text.passages'));
                }
                if ($presentableOriginalStatement->getAttachmentsDeleted()) {
                    $section->addTextBreak();
                    $section->addText($this->translator->trans('statement.anonymized.attachments'));
                }

                $section->addTextBreak();
                $image = $presentableOriginalStatement->getImage();
                if (null !== $image) {
                    $docxImageTag = $this->getDocxImageTag($image);
                    Html::addHtml($section, $docxImageTag);
                    $section->addText($this->translator->trans('map.attribution.exports', [
                        'currentYear' => date('Y'),
                    ]));
                }
            }
        }

        return $phpWord;
    }

    /**
     * Einzelne Stellungnahme mit Dateiupload.
     *
     * @param string|array $fParams
     *
     * @throws MessageBagException
     * @throws StatementElementNotFoundException
     */
    public function singleStatementHandler(string $ident, array $rParams, $fParams): array
    {
        $statement = $this->statementService->getStatement($ident);
        if (null === $statement) {
            return [];
        }

        $user = $this->currentUser->getUser();

        $orgaId = $user->getOrganisationId();
        if (!$this->isOrgaAuthorized($statement->getProcedure()->getId(), $orgaId)) {
            throw new Exception('NoAccess', 1000);
        }

        if (array_key_exists('files', $fParams) && null !== $fParams['files']) {
            $rParams['fileupload'] = $fParams['files'];
        }
        if (array_key_exists(StatementAttachment::SOURCE_STATEMENT, $fParams) && null !== $fParams[StatementAttachment::SOURCE_STATEMENT]) {
            $rParams['fileupload_'.StatementAttachment::SOURCE_STATEMENT] = $fParams[StatementAttachment::SOURCE_STATEMENT];
        }
        if (array_key_exists('r_email_attachments', $fParams)) {
            $attachments = $fParams['r_email_attachments'];
            if ((is_string($attachments) || is_array($attachments))
                && '' !== $attachments
                && [] !== $attachments) {
                $rParams['emailAttachments'] = is_string($attachments) ? [$attachments] : $attachments;
            }
        }

        $rParams['case_worker'] = $user->getFullname();

        $this->assessmentTableServiceStorage->executeAdditionalSingleViewAction($rParams);

        return $this->statementService->getStatementByIdent($ident);
    }

    /**
     * Is user authorized.
     *
     * @param string $procedureId
     * @param string $orgaId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function isOrgaAuthorized($procedureId, $orgaId)
    {
        $serviceProcedureResult = $this->procedureHandler->getProcedure($procedureId, false);

        if (null == $serviceProcedureResult) {
            return false;
        }

        $planningOfficeIds = [];
        $dataInputOrganisationIds = [];

        if (isset($serviceProcedureResult['planningOffices'])) {
            foreach ($serviceProcedureResult['planningOffices'] as $planningOffice) {
                $planningOfficeIds[] = $planningOffice['ident'];
            }
        }

        if (isset($serviceProcedureResult['dataInputOrganisations'])) {
            foreach ($serviceProcedureResult['dataInputOrganisations'] as $dataInputOrganisation) {
                $dataInputOrganisationIds[] = $dataInputOrganisation->getId();
            }
        }

        $ownerOfProcedure = array_key_exists('orgaId', $serviceProcedureResult) ? $serviceProcedureResult['orgaId'] === $orgaId : false;
        $planningOfficerOfProcedure = in_array($orgaId, $planningOfficeIds);
        $dataInputOrgaOfProcedure = in_array($orgaId, $dataInputOrganisationIds);

        if ($ownerOfProcedure || $planningOfficerOfProcedure || $dataInputOrgaOfProcedure) {
            return true;
        }

        return false;
    }

    public function replaceDataOfEsStatementFields(array $statement): array
    {
        return $this->replaceAuthoredDateOfStatementMeta($this->replacePhase($statement));
    }

    /**
     * Ersetze die Phase, in der die SN eingegangen ist.
     *
     * @return array[]
     */
    public function replacePhase(array $statement): array
    {
        $statement['phase'] = $this->statementService->getPhaseNameFromArray($statement);

        return $statement;
    }

    /**
     * Returns a formatted date. Uses the 'authoredDate' entry in the array if existent or the 'submitDateString'.
     */
    public function replaceAuthoredDateOfStatementMeta(array $statement): array
    {
        $statement['meta']['authoredDate'] = '';
        if (isset($statement['submitDateString'])) {
            $this->logger->debug('Use submitDate: '.$statement['submitDateString']);

            $statement['meta']['authoredDate'] = $statement['submitDateString'];
        }
        if (isset($statement['meta']['authoredDate'])
            && 100000 < $statement['meta']['authoredDate']
            && 3 < strlen((string) $statement['meta']['authoredDate'])
        ) {
            // authored-dates apparently arrive in iso-format
            $date = $statement['meta']['authoredDate'];
            $date = is_string($date) ? strtotime($date) : $date;
            $this->logger->debug('Found valid authoredDate: '.$date);
            $this->logger->debug('authoredDate (formatted): '.date('d.m.Y', $date));

            $statement['meta']['authoredDate'] = date('d.m.Y', $date);
        }

        return $statement;
    }

    /**
     * Bürger und Gäste bekommen den externen Namen angezeigt.
     */
    public function selectProcedureName(Procedure $procedure, bool $isPublicUser): string
    {
        return $isPublicUser ? $procedure->getExternalName() : $procedure->getName();
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
        $this->docxExporter->setProcedureHandler($this->getProcedureHandler());

        return $this->docxExporter->generateDocx(
            $outputResult,
            $templateName,
            $anonym,
            $numberStatements,
            $exportType,
            $viewOrientation,
            $requestPost,
            $sortType,
            $viewMode
        );
    }

    protected function getExportPageHeader(Procedure $procedure): string
    {
        return htmlspecialchars(
            $procedure->getOrgaName().' - '.$procedure->getName()
            .' - '.$this->translator->trans('as.at').': '.date('d.m.Y')
        );
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

    /**
     * @var array Style, wie Tabelle im gesamten aussehen soll
     */
    protected $tableStyle = [
        'borderColor' => '000000',
        'borderSize'  => 6,
        'cellMargin'  => 80,
    ];

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
                $file = $this->serviceFiles->getFileInfo($hash);
            } catch (Exception) {
                $this->logger->warning('Could not find file for hash');

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
     * @return Closure
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
                    $this->translator->trans($transKey)
                    .$delimiter.$concatValue
                ),
                $fStyle,
                $pStyle
            );
        };
    }

    /**
     * Generate Html imagetag to be used in PhphWord Html::addHtml().
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
                $width /= $factor;
                $height /= $factor;
            }
            $this->logger->info('Docx Image resize to width: '.$width.' and height: '.$height);
        }

        return '<img height="'.$height.'" width="'.$width.'" src="'.$imageFile.'"/>';
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
            'type'                          => 'statement',
            'attachments'                   => $statement['attachments'] ?? null,
            'authoredDate'                  => $statement['meta']['authoredDate'] ?? null,
            'cluster'                       => $statement['cluster'] ?? null,
            'documentTitle'                 => $statement['document']['title'] ?? null,
            'externId'                      => $statement['externId'] ?? null,
            'formerExternId'                => $statement['formerExternId'] ?? null,
            'elementTitle'                  => $statement['element']['title'] ?? null,
            'files'                         => $statement['files'] ?? null,
            'orgaName'                      => $statement['meta']['orgaName'] ?? null,
            'orgaDepartmentName'            => $statement['meta']['orgaDepartmentName'] ?? null,
            'originalId'                    => $statement['original']['ident'] ?? null,
            'paragraphTitle'                => $statement['paragraph']['title'] ?? null,
            'parentId'                      => $statement['parent']['ident'] ?? null,
            'polygon'                       => $statement['polygon'] ?? null,
            'publicAllowed'                 => $statement['publicAllowed'] ?? null,
            'publicCheck'                   => $statement['publicCheck'] ?? null,
            'publicStatement'               => $statement['publicStatement'] ?? null,
            'publicVerified'                => $statement['publicVerified'] ?? null,
            'publicVerifiedTranslation'     => $statement['publicVerifiedTranslation'] ?? null,
            'recommendation'                => $statement['recommendation'] ?? null,
            'submit'                        => $statement['submit'] ?? null,
            'submitName'                    => $statement['meta']['submitName'] ?? null,
            'authorName'                    => $statement['meta']['authorName'] ?? null,
            'text'                          => $statement['text'] ?? null,
            'votes'                         => $statement['votes'] ?? null,
            'votesNum'                      => $statement['votesNum'] ?? null,
            'likesNum'                      => $statement['likesNum'] ?? null,
            'fragments'                     => [],
            'userState'                     => $statement['meta']['userState'] ?? null,
            'userOrganisation'              => $statement['meta']['userOrganisation'] ?? null,
            'userGroup'                     => $statement['meta']['userGroup'] ?? null,
            'movedToProcedureName'          => $statement['movedToProcedureName'] ?? null,
            'movedFromProcedureName'        => $statement['movedFromProcedureName'] ?? null,
            'userPosition'                  => $statement['meta']['userPosition'] ?? null,
            'isClusterStatement'            => $statement['isClusterStatement'] ?? null,
            'name'                          => $statement['name'] ?? null,
            'isSubmittedByCitizen'          => $statement['isSubmittedByCitizen'] ?? null,
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
     * Collects Departments and usernames of clustered statements.
     *
     * @param array $item
     *
     * @return Collection
     */
    public function collectClusterOrgaOutputForExport($item)
    {
        $departments = collect([]);
        $translator = $this->translator;
        foreach ($item['cluster'] as $clusteredStatement) {
            if (array_key_exists('publicStatement', $clusteredStatement)
                && Statement::EXTERNAL === $clusteredStatement['publicStatement']) {
                // set 'Bürger'
                $key = $translator->trans('public').': '.$translator->trans(
                    'role.citizen'
                );
            } else {
                // set OrgaName
                $clusteredStatement['oName'] =
                    (!array_key_exists(
                        'oName',
                        $clusteredStatement
                    ) || '' == $clusteredStatement['oName']) ?
                        $translator->trans('not.specified') :
                        $clusteredStatement['oName'];

                // set DepartmentName
                $clusteredStatement['dName'] =
                    (!array_key_exists(
                        'dName',
                        $clusteredStatement
                    ) || '' == $clusteredStatement['dName']) ?
                        $translator->trans('not.specified') :
                        $clusteredStatement['dName'];

                $key = $translator->trans(
                    'institution'
                ).': '.$clusteredStatement['oName'].', '.$clusteredStatement['dName'];
            }

            // collect usernames in departments of orgas
            // if orga + department not already in collection:
            if (!$departments->has($key)) {
                $departments->put($key, collect([]));
            }

            // collect names of users under orga+department
            $departments->get($key)->push($clusteredStatement['uName']);
        }

        return $departments;
    }

    /**
     * Collects Ids, Names and Dates of clustered statements.
     */
    public function collectClusteredStatementMetaDataForExport(array $statement): Collection
    {
        $idsOfCluster = collect([]);
        foreach ($statement['cluster'] as $clusteredStatement) {
            $idString = $this->getIdString($clusteredStatement);
            $nameString = $this->getNameString($clusteredStatement, true);
            $dateString = $this->getDateString($clusteredStatement, true);

            $idsOfCluster->push($idString.$nameString.$dateString);
        }

        return $idsOfCluster;
    }

    /**
     * @return ProcedureHandler
     */
    protected function getProcedureHandler()
    {
        return $this->procedureHandler;
    }

    public function getSortingSet(?array $sortingSet): ?array
    {
        if (null !== $sortingSet) {
            // We take the last sorting set from statementListHandler as active sorting set
            // there should be just one
            $set = null;
            foreach ($sortingSet as $set) {
                $sortTranslationKey = 'DESC' === $set['sorting']
                    ? 'descending'
                    : 'ascending';
                $set['sortingDirectionLabel'] = $this->translator->trans($sortTranslationKey);
            }

            return $set;
        }

        return $this->getDefaultSortingSet();
    }

    public function mapTable(StatementHandlingResult $table, ?AssessmentTableViewMode $viewMode, string $procedureId, $search): StatementHandlingResult
    {
        $statements = $table->getStatements();
        $totalResults = $table->getTotal();

        if (null === $viewMode) {
            $statements = $this->statementService->addSourceStatementAttachments($statements);
        }

        // give search back to fe to put it back into input field
        $tableSearch = $table->getSearch();
        if (null !== $search) {
            $tableSearch = $search;
        }

        // Übergib die aktive Sortierung ins Template
        /*
         * Could also do this in frontend. Also, this should be done outside the controller.
         * The problem is, that the code would be duplicated in {@link StatementHandler} and {@link StatementService}
         * because default view mode is handled via {@link AssessmentTableServiceOutput::getStatementListHandler}
         * and the structures for tags and elements are done in the $statementService->createSomeStructure().
         */
        $tableSortingSet = $this->getSortingSet($table->getSortingSet());

        $tableProcedure = $table->getProcedure();
        if ($this->permissions->hasPermission('feature_statement_cluster')) {
            $tableProcedure['clusterStatements'] = $this->statementHandler->getClustersOfProcedure($procedureId);
        }

        return StatementHandlingResult::create(
            $tableProcedure,
            $tableSearch,
            $table->getFilterSet(),
            $tableSortingSet,
            $table->getSearchFields(),
            $totalResults,
            $statements,
            $table->getPager(),
            $table->getNavigation()
        );
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

    private function createFormerProcedureSuffix($formerExternId, $nameOfFormerProcedure): string
    {
        return ' ('.$this->translator->trans('formerExternId').': '.$formerExternId.' '.$this->translator->trans('from').' '.$nameOfFormerProcedure.')';
    }

    /**
     * Returns the default active sorting set for the assessment table.
     */
    public function getDefaultSortingSet(): array
    {
        return [
            'sorting'               => 'DESC',
            'sortingDirectionLabel' => $this->translator->trans('descending'),
            'name'                  => 'submitDate',
        ];
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
        $idString = $this->translator->trans('id').': '.$this->createExternIdString($statement);

        return $leadingComma ? ', '.$idString : $idString;
    }

    /**
     * Generates a String including the externalId of the incoming statement.
     * This includes logic to set "Kopie von" in case of current statement
     * is a copy of a statement and placeholder statement information.
     */
    protected function getIdStringFromObject(Statement $statement): string
    {
        return $this->translator->trans('id').': '.$this->createExternIdStringFromObject($statement);
    }

    /**
     * Generates a String including the name of the incoming statement array if existing.
     * In case of no name is given, an empty string will be returned.
     *
     * @param bool $leadingComma - If true, the returned string will be leaded by a comma if a name is found
     */
    protected function getNameString(array $statement, $leadingComma = false): string
    {
        $nameString = '';
        if (isset($statement['name']) && '' !== $statement['name'] && null !== $statement['name']) {
            $translationKey = $statement['isClusterStatement'] || null === $statement['isClusterStatement'] ? 'cluster.name' : 'name';
            $nameString = $this->translator->trans($translationKey).': '.$statement['name'];
            $nameString = $leadingComma ? ', '.$nameString : $nameString;
        }

        return $nameString;
    }

    /**
     * Generates a String including the date of the incoming statement array if existing.
     * Will use authoredDate if set and valid timestamp otherwise the submit date of the statement.
     * In case of no of the dates are set, an empty string will be returned.
     * Will use 100000 to avoid "nearly 0" timestamps generated by ancient java service.
     *
     * @param bool $leadingComma - If true, the returned string will be leaded by a comma if a date is found
     */
    protected function getDateString(array $statement, $leadingComma = false): string
    {
        $dateString = '';
        // use authoredDate if set and valid timestamp. use 100000 to avoid "nearly 0" timestamps generated by ancient java service
        if (isset($statement['authoredDate']) && 3 < strlen((string) $statement['authoredDate']) && 100000 < $statement['authoredDate']) {
            // authored-dates apparently arrive in iso-format
            $date = $statement['authoredDate'];
            $date = is_string($date) ? strtotime($date) : $date;
            $this->logger->debug('Found valid authoredDate: '.$date);
            $this->logger->debug('authoredDate (formatted): '.date('d.m.Y', $date));
            $dateString = $this->translator->trans('date').': '.date('d.m.Y', $date);
        } elseif (isset($statement['submit'])) {
            $this->logger->debug('Use submitDate: '.$statement['submit']);
            $this->logger->debug('submitDate (formatted): '.date('d.m.Y', substr((string) $statement['submit'], 0, 10)));
            $dateString = $this->translator->trans('date').': '.
                date('d.m.Y', substr((string) $statement['submit'], 0, 10));
        }

        return $leadingComma && '' !== $dateString ? ', '.$dateString : $dateString;
    }

    private function addRow(Table $table, ValuedLabel $valuedLabel, array $valueFontStyle = [], ?int $endnoteRef = null): void
    {
        $styles = $this->getDefaultDocxPageStyles(ViewOrientation::createPortrait());
        $secondCellWidth = $styles['cellWidthSecondThird'];
        $firstCellWidth = $styles['cellWidthTotal'] - $secondCellWidth;
        $row = $table->addRow(null, ['space' => ['before' => 0, 'after' => 0]]);
        $keyCell = $row->addCell($firstCellWidth);
        $keyCell->addText(htmlspecialchars((string) $valuedLabel->getLabel()));
        $valueCell = $row->addCell($secondCellWidth);
        $valueTextRun = $valueCell->addTextRun();
        $valueTextRun->addText(htmlspecialchars((string) $valuedLabel->getValue()), $valueFontStyle);
        if (null !== $endnoteRef) {
            $valueTextRun->addText($endnoteRef, ['superScript' => true]);
        }
    }

    private function hasArrayKeyInfo(array $data, string $key): bool
    {
        return isset($data[$key]) && null !== $data[$key] && '' !== $data[$key];
    }
}
