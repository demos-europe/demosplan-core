<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use Carbon\Carbon;
use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphExporter;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\News\ServiceOutput as NewsOutput;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceOutput as ProcedureOutput;
use demosplan\DemosPlanCoreBundle\Logic\Report\ExportReportService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementListUserFilter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresTranslatorTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\DocxExportResult;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Doctrine\Common\Collections\Collection;
use Exception;
use Faker\Provider\Uuid;
use Monolog\Logger;
use Patchwork\Utf8;
use PhpOffice\PhpWord\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use ZipStream\ZipStream;

class ExportService
{
    use RequiresTranslatorTrait;

    /**
     * The maximum length a procedure name to be used as folder name may have before it is
     * shortened.
     */
    private const MAX_PROCEDURE_NAME_LENGTH = 50;

    /**
     * @var array
     */
    protected $literals = [];
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ProcedureOutput
     */
    protected $procedureOutput;

    /**
     * @var NewsOutput NewsOutput
     */
    protected $newsOutput;

    /**
     * @var AssessmentTableServiceOutput
     */
    protected $assessmentTableOutput;

    /**
     * @var ParagraphExporter
     */
    protected $paragraphExporter;

    /**
     * @var DraftStatementService DraftStatementService
     */
    protected $draftStatementService;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    public function __construct(
        private readonly AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserInterface $currentUser,
        DraftStatementService $draftStatementService,
        private readonly ElementsService $elementsService,
        private readonly ExportReportService $exportReportService,
        FileService $serviceFile,
        LoggerInterface $logger,
        NewsOutput $newsOutput,
        ParagraphExporter $paragraphExporter,
        PermissionsInterface $permissions,
        ProcedureOutput $procedureOutput,
        private readonly ProcedureService $procedureService,
        private readonly StatementService $statementService,
        TranslatorInterface $translator,
        private readonly ZipExportService $zipExportService,
        private readonly string $rendererName,
        private readonly string $rendererPath,
    ) {
        $this->assessmentTableOutput = $assessmentTableServiceOutput;
        $this->draftStatementService = $draftStatementService;
        $this->logger = $logger;
        $this->newsOutput = $newsOutput;
        $this->paragraphExporter = $paragraphExporter;
        $this->permissions = $permissions;
        $this->procedureOutput = $procedureOutput;
        $this->fileService = $serviceFile;
        $this->translator = $translator;
    }

    /**
     * Loads the attribute $literals with values.
     */
    private function loadLiterals(): void
    {
        // Dictonary with keys to obtain
        $dictionary = [
            'statements'         => 'statements',
            'considerationtable' => 'considerationtable_ascii',
            'originals'          => 'statements.original',
            'attachment'         => 'attachment',
            'elements'           => 'elements',
            'finals'             => 'statements.final_versions',
        ];

        // Obtain translation strings
        foreach ($dictionary as $key => $transKey) {
            $this->literals[$key] = Utf8::toAscii($this->getTranslator()->trans($transKey));
        }
    }

    /**
     * Export Procedure.
     *
     * @param string|string[] $procedureIds             A procedure ID as string or one or multiple
     *                                                  procedure IDs in an array. The behavior for
     *                                                  an empty array is undefined.
     * @param bool            $useExternalProcedureName true if {@link Procedure::$externalName}
     *                                                  should be used instead of
     *                                                  {@link Procedure::$name}
     *
     * @return bool|ZipStream exportfolder path from Export
     *
     * @throws Exception
     */
    public function createProcedureExportJob($procedureIds, bool $useExternalProcedureName, ZipStream $zip)
    {
        $this->loadLiterals();

        // Create Zip Archive
        $zipFolderAbsolute = $this->fileService->getFilesPathAbsolute().'/export';
        $hash = md5(random_int(0, 9999).time());
        $zipPath = $zipFolderAbsolute.'/'.$hash.'.zip';
        $this->logger->info('createProcedureExportJob $zipPath: '.$zipPath);
        // Check if Folder exists, if not, create
        if (!is_dir($zipFolderAbsolute)) {
            $fs = new DemosFilesystem();
            try {
                $fs->mkdir($zipFolderAbsolute);
            } catch (Exception $e) {
                $this->logger->warning('Could not create Directory: ', [$e]);
            }
        }

        // should be empty, because this method is triggered from procedure list.
        $storedProcedure = $this->currentProcedureService->getProcedure();

        $startTime = microtime(true);
        $procedureIds = is_array($procedureIds) ? $procedureIds : [$procedureIds];
        foreach ($procedureIds as $procedureId) {
            $procedureToExport = $this->procedureService->getProcedure($procedureId);
            if ($procedureToExport instanceof Procedure) {
                $this->permissions->setProcedure($procedureToExport);
                $this->permissions->checkProcedurePermission();

                $procedureAsArray = $this->getProcedureOutput()->getProcedureWithPhaseNames($procedureId);
                $procedureNameField = $useExternalProcedureName ? 'externalName' : 'name';
                $procedureName = $this->toExportableProcedureName($procedureAsArray[$procedureNameField], $procedureId);
                $this->logger->info('Creating Zips for Procedure', ['id' => $procedureId, 'name' => $procedureName]);

                // get all PDFs

                // Institutionen-Liste
                if ($this->permissions->hasPermission('feature_procedure_export_include_public_interest_bodies_member_list')) {
                    $zip = $this->addMemberListToZip($procedureId, $procedureName, $zip);
                }

                // Titelblatt
                if ($this->permissions->hasPermission('feature_procedure_export_include_cover_page')) {
                    $zip = $this->addTitlePageToZip($procedureId, $procedureName, $zip);
                }

                // Aktuelles
                if ($this->permissions->hasPermission('feature_procedure_export_include_current_news')) {
                    $zip = $this->addNewsToZip($procedureId, $procedureName, $zip);
                }

                // Abwägungstabelle mit Namen
                if ($this->permissions->hasPermission('feature_procedure_export_include_assessment_table')) {
                    $zip = $this->addAssessmentTableToZip($procedureId, $procedureName, 'statementsOnly', $zip);
                }

                if ($this->permissions->hasPermission('feature_procedure_export_include_assessment_table_fragments')) {
                    $zip = $this->addAssessmentTableToZip($procedureId, $procedureName, 'statementsAndFragments', $zip);
                }

                // Abwägungstabelle ohne Namen (anonym)
                if ($this->permissions->hasPermission('feature_procedure_export_include_assessment_table_anonymous')) {
                    $zip = $this->addAssessmentTableAnonymousToZip($procedureId, $procedureName, 'statementsOnly', $zip);
                }

                if ($this->permissions->hasPermission('feature_procedure_export_include_assessment_table_fragments_anonymous')) {
                    $zip = $this->addAssessmentTableAnonymousToZip($procedureId, $procedureName, 'statementsAndFragments', $zip);
                }

                // OriginalStellungnahmen
                if ($this->permissions->hasPermission('feature_procedure_export_include_assessment_table_original')) {
                    $zip = $this->addAssessmentTableOriginalToZip($procedureId, $procedureName, $zip);
                }

                // Paragraph Elements
                $zip = $this->addParagraphElementsToZip($procedureId, $procedureName, $zip);

                // Planzeichnung
                $zip = $this->addMapToZip($procedureId, $procedureName, $zip);

                // Planunsgdokumente ()
                $zip = $this->addAllPlanningDocumentsToZip($procedureId, $procedureName, $zip);

                // Stellungnahmen ToeB (Endfassungen)
                if ($this->permissions->hasPermission('feature_procedure_export_include_statement_final_group')) {
                    $zip = $this->addStatementsFinalGroupToZip($procedureId, $procedureName, $zip);
                }
                // Stellungnahmen ToeB (Freigaben)
                if ($this->permissions->hasPermission('feature_procedure_export_include_statement_released')) {
                    $zip = $this->addStatementsReleasedToZip($procedureId, $procedureName, $zip);
                }
                // Stellungnahmen Buerger (Endfassungen)
                if ($this->permissions->hasPermission('feature_procedure_export_include_public_statements')) {
                    $zip = $this->addPublicStatementsToZip($procedureId, $procedureName, $zip);
                }
                // reports
                if ($this->permissions->hasPermission('feature_export_protocol')) {
                    $zip = $this->addReportToZip($procedureId, $procedureName, $zip);
                }
            }
        }

        $this->permissions->setProcedure($storedProcedure);

        $this->logger->info('Time needed to create ProcedureZip: '.number_format(microtime(true) - $startTime, 2).'s');

        return $zip;
    }

    protected function getProcedureOutput(): ?ProcedureOutput
    {
        return $this->procedureOutput;
    }

    public function addMemberListToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        $title = $this->getInstitutionListPhrase();

        if (!$this->permissions->hasPermission('feature_institution_participation')) {
            return $zip;
        }

        try {
            $memberList = $this->getProcedureOutput()->generatePdfForMemberList($procedureId, [], $title);
            $this->zipExportService->addStringToZipStream("$procedureName/$title.pdf", $memberList, $zip);
            $this->logger->info('toeb_benutzer_liste created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('toeb_benutzer_liste could not be created. ', [$e]);
        }

        return $zip;
    }

    /**
     * Accesses the project dependent term for institutions and slugifies
     * it to be usable in file names (as it may contain umlauts).
     */
    public function getInstitutionListPhrase(): string
    {
        $institutionTerm = $this->translator->trans('invitable_institution');
        $slugifier = new Slugify(['lowercase' => false]);
        $institutionTerm = $slugifier->slugify($institutionTerm);

        return "$institutionTerm-Liste";
    }

    /**
     * This method includes a cover page ("Deckblatt.pdf") in the procedure export
     * in each procedure directory.
     * Possibly @improve T20979.
     */
    public function addTitlePageToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        $titleForPage = 'titlepage';

        if (!$this->permissions->hasPermission('feature_institution_participation')) {
            return $zip;
        }

        try {
            $titlepage = $this->getProcedureOutput()->generatePdfForTitlePage($procedureId, $titleForPage);
            $this->zipExportService->addStringToZipStream($procedureName.'/Deckblatt.pdf', $titlepage, $zip);
            $this->logger->info('deckblatt created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('deckblatt could not be created. ', [$e]);
        }

        return $zip;
    }

    /**
     * Add a document containing news ("Aktuelles.pdf") to each procedure
     * directory in the export.
     */
    public function addNewsToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        $manualSortScope = 'procedure:'.$procedureId;
        try {
            $news = $this->newsOutput->generatePdf(
                $procedureId,
                $manualSortScope,
                'news'
            );
            $this->zipExportService->addStringToZipStream($procedureName.'/Aktuelles.pdf', $news, $zip);
            // Save Files
            $outputResult = $this->newsOutput->newsListHandler($procedureId, $manualSortScope);

            foreach ($outputResult as $news) {
                if (0 !== strlen((string) $news['pdf'])) {
                    $this->zipExportService->addFilePathToZipStream($news['pdf'], $procedureName.'/Anhang/Aktuelles', $zip);
                }

                if (0 !== strlen((string) $news['picture'])) {
                    $this->zipExportService->addFilePathToZipStream($news['picture'], $procedureName.'/Anhang/Aktuelles', $zip);
                }
            }

            $this->logger->info('aktuelles created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('aktuelles could not be created. ', [$e]);
        }

        return $zip;
    }

    public function addAssessmentTableToZip(
        string $procedureId,
        string $procedureName,
        string $exportType,
        ZipStream $zip,
    ): ZipStream {
        $rParams = [
            'filters' => [],
            'request' => ['limit' => 1_000_000],
            'items'   => [],
            'sort'    => ToBy::createArray('submitDate', 'desc'),
        ];

        $type = [
            'anonymous'        => false,
            'numberStatements' => false,
            'exportType'       => $exportType,
            'template'         => 'condensed',
            'sortType'         => AssessmentTableServiceOutput::EXPORT_SORT_DEFAULT,
        ];

        try {
            $exportResult = $this->assessmentHandler->exportDocx(
                $procedureId,
                $rParams,
                $type,
                AssessmentTableViewMode::DEFAULT_VIEW,
                false
            );
            $filename = $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['considerationtable'].'/%s.docx';
            switch ($exportType) {
                case 'statementsOnly':
                    $filename = sprintf($filename, $this->literals['considerationtable'].'_Liste');
                    break;

                case 'statementsAndFragments':
                    $filename = sprintf($filename, $this->literals['considerationtable'].'_Liste_mit_Datensaetzen');
                    break;
            }

            $this->addDocxToZip($exportResult, $zip, $filename);
            $this->logger->info('abwaegung_list created',
                ['exportType' => $exportType, 'id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning("abwaegung_list for type $exportType could not be created", [$e]);
        }

        return $zip;
    }

    public function addAssessmentTableAnonymousToZip(
        string $procedureId,
        string $procedureName,
        string $exportType,
        ZipStream $zip,
    ): ZipStream {
        $type = [
            'anonymous'        => true,
            'numberStatements' => false,
            'exportType'       => $exportType,
            'template'         => 'condensed',
            'sortType'         => AssessmentTableServiceOutput::EXPORT_SORT_DEFAULT,
        ];

        $rParams = [
            'filters' => [],
            'request' => ['limit' => 1_000_000],
            'items'   => [],
            'sort'    => ToBy::createArray('submitDate', 'desc'),
        ];

        try {
            $exportResult = $this->assessmentHandler->exportDocx(
                $procedureId,
                $rParams,
                $type,
                AssessmentTableViewMode::DEFAULT_VIEW,
                false
            );
            $filename = $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['considerationtable'].'/%s.docx';
            switch ($exportType) {
                case 'statementsOnly':
                    $filename = sprintf($filename, $this->literals['considerationtable'].'_Liste_Anonym');
                    break;

                case 'statementsAndFragments':
                    $filename = sprintf($filename, $this->literals['considerationtable'].'_Liste_mit_Datensaetzen_Anonym');
                    break;
            }

            $this->addDocxToZip($exportResult, $zip, $filename);

            // Save Files
            $outputResult = $this->assessmentTableOutput->getStatementListHandler($procedureId, $rParams);
            $statementEntities = $this->arrayFormatsToEntities(
                $outputResult->getStatements(),
                $this->statementService->getStatementsByIds(...)
            );
            $folderName = $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['considerationtable'].'/'.$this->literals['attachment'].'/';
            $this->attachStatementFilesToZip($statementEntities, $folderName, $zip);

            $this->logger->info('abwaegung_list_anonym created',
                ['exportType' => $exportType, 'id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning("abwaegung_list_anonym for $exportType could not be created.", [$e]);
        }

        return $zip;
    }

    public function addAssessmentTableOriginalToZip(
        string $procedureId,
        string $procedureName,
        ZipStream $zip,
    ): ZipStream {
        $rParams = [
            'filters' => ['original' => 'IS NULL'],
            'request' => ['limit' => 1_000_000],
            'items'   => [],
        ];

        try {
            $exportResult = $this->assessmentHandler->generateOriginalStatementsDocx($procedureId);
            $filename = $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['originals'].'/'.$this->literals['originals'].'_Liste.docx';
            $this->addDocxToZip($exportResult, $zip, $filename);
            $outputResult = $this->assessmentTableOutput->getStatementListHandler($procedureId, $rParams);
            $statementEntities = $this->arrayFormatsToEntities(
                $outputResult->getStatements(),
                $this->statementService->getStatementsByIds(...)
            );
            $this->attachStatementFilesToZip($statementEntities, $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['originals'].'/Anhang/', $zip);
            $this->logger->info('abwaegung_list_original created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('abwaegung_list_original could not be created. ', [$e]);
        }

        return $zip;
    }

    /**
     * The procedure export generates a ZIP archive. This method includes
     * a directory "Planunterlagen" in each procedure directory
     * in the export. These directories will contain an additional directory
     * for each document category (like "Begründung" or "Ergänzende Unterlagen") if
     * it contains documents attached to tha category. These documents will be
     * included in the category directory.
     */
    public function addParagraphElementsToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        try {
            $user = $this->currentUser->getUser();
            $organisationId = $user->getOrganisationId();
            $procedureElements = $this->elementsService->getElementsListObjects($procedureId, $organisationId);
            foreach ($procedureElements as $procedureElement) {
                if ('paragraph' === $procedureElement->getCategory()) {
                    $elementTitle = $procedureElement->getTitle();
                    try {
                        $elementFile = $procedureElement->getFile();
                        if (0 !== strlen($elementFile)) {
                            $this->zipExportService->addFilePathToZipStream($elementFile, $procedureName.'/'.$this->literals['elements'].'/'.$elementTitle, $zip);
                        }
                        $agreement = $this->paragraphExporter->generatePdf($procedureId, $elementTitle, $procedureElement->getId());
                        if (null !== $agreement) {
                            $this->zipExportService->addStringToZipStream($procedureName.'/'.$this->literals['elements'].'/'.Utf8::toAscii($elementTitle).'.pdf', $agreement, $zip);
                            $this->logger->info('ParagraphElement created',
                                ['elementTitle' => $elementTitle, 'id' => $procedureId, 'name' => $procedureName]);
                        } else {
                            $this->logger->info('ParagraphElement ignored, because it has no paragraphs.',
                                ['elementTitle' => $elementTitle, 'id' => $procedureId, 'name' => $procedureName]);
                        }
                    } catch (Exception $e) {
                        $this->logger->warning("ParagraphElement $elementTitle could not be created.", [$e]);
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('Failed to create ParagraphElements Zip ', [$e]);
        }

        return $zip;
    }

    public function addMapToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        try {
            $procedureAsArray = $this->getProcedureOutput()->getProcedureWithPhaseNames($procedureId);
            if (0 !== strlen((string) $procedureAsArray['settings']['planDrawPDF'])) {
                $this->zipExportService->addFilePathToZipStream($procedureAsArray['settings']['planDrawPDF'], $procedureName.'/Planzeichnung', $zip);
            }
            if (0 !== strlen((string) $procedureAsArray['settings']['planPDF'])) {
                $this->zipExportService->addFilePathToZipStream($procedureAsArray['settings']['planPDF'], $procedureName.'/Planzeichnung', $zip);
            }
            $this->logger->info('planning_documents created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('planning_documents could not be created. ', [$e]);
        }

        return $zip;
    }

    public function addAllPlanningDocumentsToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        try {
            $elements = $this->elementsService->getElementsAdminList($procedureId);
            // Folgende Kategorien werden einzeln exportiert
            $categoriesNotToExport = ['statement', 'paragraph', 'map'];
            foreach ($elements as $element) {
                if (!in_array($element->getCategory(), $categoriesNotToExport)) {
                    if (!$this->mayExportPlanningDocumentCategory($element)) {
                        continue;
                    }

                    foreach ($element->getDocuments() as $document) {
                        if (!$this->mayExportPlanningDocumentFile($document)) {
                            continue;
                        }
                        $this->zipExportService->addFilePathToZipStream(
                            $document->getDocument(),
                            $procedureName.'/'.$this->literals['elements'].'/'.$element->getTitle(),
                            $zip
                        );
                    }
                }
            }
            $this->logger->info('planning_documents created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('planning_documents could not be created. ', [$e]);
        }

        return $zip;
    }

    private function mayExportPlanningDocumentCategory(Elements $element): bool
    {
        // user may always export anything from own procedure
        if ($this->permissions->ownsProcedure()) {
            return true;
        }

        // do not export disabled or deleted planning document categories
        if (!$element->getEnabled() || $element->getDeleted()) {
            return false;
        }

        $organisations = $element->getOrganisations();
        if ($organisations->isEmpty()) {
            return true;
        }

        $currentUserOrgaId = $this->currentUser->getUser()->getOrganisationId();
        $containsCurrentUserOrga = collect($organisations)->filter(static fn (Orga $orga) => $orga->getId() === $currentUserOrgaId);

        return 0 < $containsCurrentUserOrga->count();
    }

    private function mayExportPlanningDocumentFile(SingleDocument $document): bool
    {
        // user may always export any documents from own procedure
        if ($this->permissions->ownsProcedure()) {
            return true;
        }

        return $document->getVisible() && !$document->getDeleted();
    }

    public function addStatementsFinalGroupToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        try {
            $draftStatementService = $this->draftStatementService;
            $filters = new StatementListUserFilter();
            $filters->setReleased(true)->setSubmitted(true);
            $user = $this->currentUser->getUser();
            $outputResult = $draftStatementService->getDraftStatementList($procedureId, 'group', $filters, null, null, $user);
            $draftStatementEntities = $this->arrayFormatsToEntities(
                $outputResult->getResult(),
                $this->draftStatementService->getByIds(...)
            );
            $statementsFinalPdf = $draftStatementService->generatePdf($outputResult->getResult(), 'list_final_group', $procedureId);
            $this->zipExportService->addStringToZipStream(
                $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['finals'].'/'.$this->literals['finals'].'_Gruppe_Liste.pdf',
                $statementsFinalPdf->getContent(),
                $zip
            );
            $this->attachStatementFilesToZip($draftStatementEntities, $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['finals'].'/'.$this->literals['attachment'].'/', $zip);
            $this->logger->info('Endfassungen_Gruppe created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('Endfassungen_Gruppe could not be created. ', [$e]);
        }

        return $zip;
    }

    public function addStatementsReleasedToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        try {
            $draftStatementService = $this->draftStatementService;
            $filters = new StatementListUserFilter();
            $filters->setSubmitted(false)->setReleased(true);
            $user = $this->currentUser->getUser();
            $outputResult = $draftStatementService->getDraftStatementList($procedureId, 'group', $filters, null, null, $user);
            $draftStatementEntities = $this->arrayFormatsToEntities(
                $outputResult->getResult(),
                $this->draftStatementService->getByIds(...)
            );
            $statementsReleasedPdf = $draftStatementService->generatePdf($outputResult->getResult(), 'list_released_group', $procedureId);
            $this->zipExportService->addStringToZipStream(
                $procedureName.'/'.$this->literals['statements'].'/Freigaben/Freigaben_Gruppe_Liste.pdf',
                $statementsReleasedPdf->getContent(),
                $zip
            );
            $this->attachStatementFilesToZip($draftStatementEntities, $procedureName.'/'.$this->literals['statements'].'/Freigaben/'.$this->literals['attachment'].'/', $zip);
            $this->logger->info('Freigaben_Gruppe created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('Freigaben_Gruppe could not be created. ', [$e]);
        }

        return $zip;
    }

    public function addPublicStatementsToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        try {
            $draftStatementService = $this->draftStatementService;
            $user = $this->currentUser->getUser();
            $filters = new StatementListUserFilter();
            $filters->setReleased(true)->setSubmitted(true);
            $outputResult = $draftStatementService->getDraftStatementList($procedureId, 'ownCitizen', $filters, null, null, $user);
            $statementEntities = $this->arrayFormatsToEntities(
                $outputResult->getResult(),
                $this->draftStatementService->getByIds(...)
            );
            $statementsCitizenPdf = $draftStatementService->generatePdf($outputResult->getResult(), 'list_final_group_citizen', $procedureId);
            $this->zipExportService->addStringToZipStream(
                $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['finals'].'_Buerger/Freigaben_Buerger_Liste.pdf', $statementsCitizenPdf->getContent(),
                $zip
            );
            $this->attachStatementFilesToZip($statementEntities, $procedureName.'/'.$this->literals['statements'].'/'.$this->literals['finals'].'_Buerger/'.$this->literals['attachment'].'/', $zip);
            $this->logger->info('Endfassungen_Buerger created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('Endfassungen_Buerger could not be created. ', [$e]);
        }

        return $zip;
    }

    public function addReportToZip(string $procedureId, string $procedureName, ZipStream $zip): ZipStream
    {
        try {
            /** @var Procedure $procedure */
            $procedure = $this->procedureService->getProcedure($procedureId);
            if (null === $procedure) {
                throw new InvalidArgumentException('No procedure found.');
            }

            $currentTime = Carbon::now();
            $reportMeta = [
                'name'       => $procedure->getName(),
                'exportDate' => $currentTime->format('d.m.Y'),
                'exportTime' => $currentTime->format('H:i'),
            ];
            Settings::setPdfRendererPath($this->rendererPath);
            Settings::setPdfRendererName($this->rendererName);
            $reportInfo = $this->exportReportService->getReportInfo($procedureId, $this->permissions);
            $pdfReport = $this->exportReportService->generateProcedureReport($reportInfo, $reportMeta);
            $this->zipExportService->addWriterToZipStream(
                $pdfReport,
                $procedureName.'/Verfahrensprotokoll.pdf',
                $zip,
                'tmp_export_procedure_reports_',
                '.pdf'
            );

            $this->logger->info('Verfahrensprotokoll created', ['id' => $procedureId, 'name' => $procedureName]);
        } catch (Exception $e) {
            $this->logger->warning('Verfahrensprotokoll could not be created. ', [$e]);
        }

        return $zip;
    }

    /**
     * Converts the given procedure name to something safe to be used in the export.
     *
     * The procedure will be shortened name to {@link MAX_PROCEDURE_NAME_LENGTH} (if not already
     * shorter). Special/non-ASCII characters are converted and a 8 character hash based on the
     * procedure ID is appended.
     *
     * Due to the hash the result may be longer than {@link MAX_PROCEDURE_NAME_LENGTH}.
     *
     * A noteworthy example for a problematic character in procedure names is the percent character
     * (%) which would result in an 'srv' folder being included in the export.
     */
    protected function toExportableProcedureName(string $actualProcedureName, string $procedureId): string
    {
        $slugger = new Slugify(['lowercase' => false]);
        $procedureIdHash = hash('crc32', $procedureId);

        $procedureName = mb_substr($actualProcedureName, 0, self::MAX_PROCEDURE_NAME_LENGTH);
        $procedureName = "{$procedureName}_{$procedureIdHash}";
        $procedureName = Utf8::toAscii($procedureName);

        return $slugger->slugify($procedureName);
    }

    /**
     * Write Files into Attachment folder in Export.
     *
     * @param array<int,Statement|DraftStatement> $statements
     * @param string                              $folder     Must always end with a slash (/) character
     */
    protected function attachStatementFilesToZip(array $statements, string $folder, ZipStream $zip): void
    {
        $fs = new DemosFilesystem();
        foreach ($statements as $statement) {
            if ($statement instanceof Statement) {
                $fileNamePrefix = $statement->getExternId().'_';
            } elseif ($statement instanceof DraftStatement) {
                $fileNamePrefix = $statement->getNumber().'_';
            } else {
                throw new InvalidArgumentException('Unsupported item type');
            }

            if ($statement instanceof Statement) {
                $this->addSourceStatementAttachments($folder, $zip, $fileNamePrefix, $statement->getAttachments());
            }
            $this->zipExportService->addFiles($fileNamePrefix, $fs, $folder, $zip, ...($statement->getFiles() ?? []));
        }
    }

    private function addSourceStatementAttachments(
        string $fileFolderPath,
        ZipStream $zip,
        string $fileNamePrefix,
        Collection $attachments,
    ): void {
        collect($attachments)
            ->filter(
                static fn (StatementAttachment $attachment): bool => StatementAttachment::SOURCE_STATEMENT === $attachment->getType()
            )->map(
                fn (StatementAttachment $attachment): FileInfo => $this->fileService->getFileInfo(
                    $attachment->getFile()->getId()
                )
            )->each(
                function (FileInfo $fileInfo) use ($fileFolderPath, $zip, $fileNamePrefix): void {
                    $this->zipExportService->addFileToZip(
                        $fileFolderPath,
                        $fileInfo,
                        $zip,
                        $fileNamePrefix
                    );
                }
            );
    }

    private function addDocxToZip(DocxExportResult $exportResult, ZipStream $zip, string $filename): void
    {
        $writer = $exportResult->getWriter();
        $internalFilename = 'tmp_export_orig_stn_'.Uuid::uuid().'.docx';
        $filepath = DemosPlanPath::getTemporaryPath($internalFilename);
        $writer->save($filepath);
        $this->zipExportService->addFileToZipStream($filepath, $filename, $zip);
        // uses local file, no need for flysystem
        $fs = new Filesystem();
        $fs->remove($filepath);
    }

    /**
     * Works on entities with the ID stored in an 'id' field and a corresponding getter only.
     *
     * @param array[]                                   $entitiesAsArrays The entities in their array format (legacy or from elasticsearch)
     * @param callable(string[]): UuidEntityInterface[] $callable         a callback taking an array of IDs and returning the corresponding entities
     *
     * @return array<int,UuidEntityInterface> will have the same order as in the originally given array
     */
    private function arrayFormatsToEntities(array $entitiesAsArrays, callable $callable): array
    {
        $ids = array_column($entitiesAsArrays, 'id');

        $mapArray = [];
        foreach ($callable($ids) as $entity) {
            $mapArray[$entity->getId()] = $entity;
        }

        $result = [];
        foreach ($entitiesAsArrays as $array) {
            $result[] = $mapArray[$array['id']];
        }

        return $result;
    }

    /**
     * @param string[] $procedureIds
     */
    public function generateProcedureExportZip(array $procedureIds, bool $useExternalProcedureName): StreamedResponse
    {
        return $this->zipExportService->buildZipStreamResponse(
            $this->translator->trans('procedure.export_filename').'.zip',
            function (ZipStream $archive) use ($procedureIds, $useExternalProcedureName): void {
                $this->createProcedureExportJob($procedureIds, $useExternalProcedureName, $archive);
            }
        );
    }
}
