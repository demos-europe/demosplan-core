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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Exception\AsynchronousStateException;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\ErroneousDoctrineResult;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\Enum\ListLineWidth;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\Enum\TextLineWidth;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\IdCollection;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Exception;
use Illuminate\Support\Collection;
use LogicException;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AssessmentTablePdfExporter extends AssessmentTableFileExporterAbstract
{
    /** @var Environment */
    protected $twig;
    /** @var ServiceImporter */
    protected $serviceImport;

    /** @var array */
    private $supportedTypes = ['pdf'];

    public function __construct(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentProcedureService $currentProcedureService,
        // : TODO: By Config ?
        private readonly CurrentUserInterface $currentUser,
        Environment $twig,
        private readonly FileService $fileService,
        LoggerInterface $logger,
        private readonly MapService $mapService,
        private readonly PermissionsInterface $permissions,
        RequestStack $requestStack,
        ServiceImporter $serviceImport,
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
        $this->twig = $twig;
        $this->serviceImport = $serviceImport;
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function __invoke(array $parameters): array
    {
        $changedOutputResult = [];
        $pdf = [];
        try {
            $procedureId = $parameters['procedureId'];
            $anonymous = $parameters['anonymous'];
            $exportType = $parameters['exportType'];
            $template = $parameters['template'];
            $original = $parameters['original'];
            $viewMode = $parameters['viewMode'];
            if (AssessmentTableViewMode::DEFAULT_VIEW !== $viewMode) {
                throw new \InvalidArgumentException("Unsupported view mode: $viewMode");
            }
            $procedure = $this->currentProcedureService->getProcedure();
            $exportTemplateData = $this
                ->selectExportTemplateData(
                    $original,
                    $template,
                    $anonymous
                );
            $templateName = $exportTemplateData->getTemplateName();
            $filenamePrefix = $exportTemplateData->getFileNamePrefix();
            $title = $exportTemplateData->getTitle();
            $templateVars = [];
            $parameters['procedure'] = $procedureId;
            if (!array_key_exists('filters', $parameters) || !is_array($parameters['filters'])) {
                $parameters['filters'] = [];
            }
            if (!array_key_exists('statementId', $parameters)) {
                $parameters = $this->addStatementsFromCurrentQueryHashToFilter($parameters, $procedureId, $original);
            } else {
                /*
                 * in case the key 'statementId' was set by the invoking
                 * { @link AssessmentTableZipExporter::getAttachmentsOfStatements }
                 * do not try to obtain the ids from session
                 */
                if (!array_key_exists('sort', $parameters)) {
                    $parameters['sort'] = ToBy::createArray('submitDate', 'desc');
                }
                $parameters['items'] = [$parameters['statementId']];
            }

            $idCollection = $this->extractStatementAndFragmentIds($parameters);
            $fragmentIds = $idCollection->getFragmentIds();
            if (null !== $idCollection->getStatementIds()) {
                $parameters['filters']['id'] = $idCollection->getStatementIds();
            }

            $parameters['filters']['original'] = 'IS NOT NULL';
            if ($original) {
                $parameters['filters']['original'] = 'IS NULL';
            }
            $outputResult = $this->assessmentTableOutput->getStatementListHandler(
                $procedureId,
                $parameters
            );
            $statements = $outputResult->getStatements();

            // add attachments to Elasticsearch statement arrays
            $statements = $this->statementHandler->addSourceStatementAttachments($statements);

            $statements = $this->filterStatementsForCondensedExport($statements, $template, $exportType);

            $statements = array_map(
                $this->assessmentTableOutput->replacePhase(...),
                $statements
            );

            if ('condensed' === $template) {
                $statements = $this->prepareStatementsForCondensedTemplate(
                    $statements,
                    $exportType,
                    $procedureId,
                    $original,
                    $fragmentIds,
                    $anonymous,
                    $procedure
                );
            }
            $statements = $this->createExternIds($statements);
            $changedOutputResult['entries']['statements'] = $statements;
            $changedOutputResult['entries']['total'] = $outputResult->getTotal();

            $templateVars['table'] = $changedOutputResult;
            if (!$this->permissions->hasPermission('feature_statement_meta_house_number_export')) {
                foreach ($templateVars['table']['entries']['statements'] as $key => $singleStatementData) {
                    $singleStatementData['meta']['houseNumber'] = '';
                    $templateVars['table']['entries']['statements'][$key] = $singleStatementData;
                }
            }

            // Abwägungstabelle:
            // * Kompakte Ansicht Stellungnahmen: DemosPlanAssessmentTableBundle:DemosPlan:export_condensed.tex.twig
            // * Kompakte Ansicht Datensätze: DemosPlanAssessmentTableBundle:DemosPlan:export_condensed.tex.twig
            // * Querformat: DemosPlanAssessmentTableBundle:DemosPlan:export.tex.twig
            // * Hochformat: DemosPlanAssessmentTableBundle:DemosPlan:export.tex.twig
            // Originalstellungnahmen:
            // * Kompakte Ansicht: DemosPlanAssessmentTableBundle:DemosPlan:export_condensed.tex.twig
            // * Querformat: DemosPlanAssessmentTableBundle:DemosPlan:export_original.tex.twig
            // * Hochformat: DemosPlanAssessmentTableBundle:DemosPlan:export_original.tex.twig
            $fullTemplateName = '@DemosPlanCore/DemosPlanAssessmentTable/DemosPlan/'.$templateName.'.tex.twig';

            $templateVars['listwidth'] = $this->determineListLineWidth($template, $templateName, $original);
            $templateVars['textwidth'] = $this->determineTextLineWidth($template, $templateName, $original);

            $content = $this->twig->render(
                $fullTemplateName,
                [
                    'templateVars'  => $templateVars,
                    'isOriginal'    => $original,
                    'title'         => $title,
                    'procedure'     => $procedure,
                    'pdfLandscape'  => 'landscape' === $template || 'landscapeWithFrags' === $template,
                    'viewMode'      => AssessmentTableViewMode::DEFAULT_VIEW,
                    'anonymous'     => $anonymous,
                    'newPagePerStn' => array_key_exists('newPagePerStn', $parameters) ? $parameters['newPagePerStn'] : false,
                ]
            );
            $isPublicUser = $this->currentUser->getUser()->isPublicUser();
            $procedureName = $this->assessmentTableOutput->selectProcedureName($procedure, $isPublicUser);
            $pictures = $this->collectPictures(
                $changedOutputResult['entries'],
                $procedureId
            ); // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
            $pdf = $this->createPdf($content, $pictures, $filenamePrefix, $procedureName);
        } catch (Exception $e) {
            throw new DemosException('warning.export.pdf.failed', $e->getMessage());
        }

        return $pdf;
    }

    public function supports(string $format): bool
    {
        return in_array($format, $this->supportedTypes, true);
    }

    /**
     * @param array[] $statements
     *
     * @return array[]
     */
    public function createExternIds(array $statements): array
    {
        // use central method to generate part of externId for exports:
        foreach ($statements as $key => $statementArray) {
            $statements[$key]['externIdString'] = $this
                ->assessmentTableOutput
                ->createExternIdString($statementArray);
        }

        return $statements;
    }

    /**
     * Formats and collects the statements in the given array. If a statement has
     * fragments the fragments are formatted and added to the collected array instead.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/sortierung/
     *
     * @param array       $statements
     * @param bool        $statementsOnly      Ignore fragments and collect all statements instead
     * @param string|null $filterSetHash
     * @param bool        $original            Is it an original statement?
     * @param array       $selectedFragmentIds if given, only use the selected fragments
     *
     * @throws AsynchronousStateException
     * @throws ErroneousDoctrineResult
     * @throws ReflectionException
     */
    protected function collectStatementsOrFragments($statements, $statementsOnly, $filterSetHash, string $procedureId, bool $original, $selectedFragmentIds = []): Collection
    {
        $filterSetReferenceSorting = [];

        if (true !== $original) {
            $filterSetStatements = $this->statementHandler->getResultsByFilterSetHash(
                $filterSetHash,
                $procedureId
            );

            $filterSetReferenceSorting = $this->statementHandler->getStatementsAndTheirFragmentsInOneFlatList(
                $filterSetStatements,
                [StatementFragment::class, Statement::class]
            );

            $statements = $this->reorderStatementsOrStatementFragmentsAccordingToOtherList(
                $statements,
                $filterSetReferenceSorting
            );
        }

        $items = collect([]);

        // collect Statements in unified data format
        foreach ($statements as $statement) {
            // 'statementsAndFragments' or 'fragmentsOnly'
            if (true === $statementsOnly) {
                $item = $this->assessmentTableOutput->formatStatementArray($statement);
                $items->push($item);
            } else {
                if (true === $original) {
                    $warning = 'Attempted to export statement fragments while exporting original statements.'.
                        ' This doesn\'t make sense from a business logic perspective.';
                    throw new LogicException($warning);
                }

                // has Fragments
                if (0 < (is_countable($statement['fragments']) ? count($statement['fragments']) : 0)) {
                    // if there are fragments, export those and not the statement

                    $unorderedList = $this->filterForSelectedStatementFragments($statement['fragments'], $selectedFragmentIds);
                    $finalList = $this->reorderStatementsOrStatementFragmentsAccordingToOtherList(
                        $unorderedList,
                        $filterSetReferenceSorting
                    );

                    // add statements fragments to items list
                    foreach ($finalList as $fragment) {
                        $item = $this->formatFragmentArray($statement, $fragment);
                        $items->push($item);
                    }
                } else {
                    // if there are no fragments, export the entire statement

                    $item = $this->assessmentTableOutput->formatStatementArray($statement);
                    $items->push($item);
                }
            }
        }

        return $items;
    }

    /**
     * @param array[] $entries
     */
    protected function collectPictures(array $entries, string $procedureId): array
    {
        // gibt es in den Stellungnahmen Verortungen?
        $pictures = [];
        $i = 0;
        $statements = [];
        // get statements for loop
        if (array_key_exists('statements', $entries)) {
            foreach ($entries['statements'] as $statement) {
                $statements[] = $statement;
            }
        } else {
            foreach ($entries['statementGroups'] as $statementGroup) {
                foreach ($statementGroup['statements'] as $statementFragments) {
                    $statements[] = $statementFragments;
                }
            }
        }
        // loop statements / fragments
        foreach ($statements as $statement) {
            try {
                // Ignore fragments / statements. they do not have idents, only ids
                if (!isset($statement['ident'])) {
                    continue;
                }

                $mapFile = $statement['mapFile'];
                if (('' === $mapFile || null === $mapFile) && 0 < strlen((string) $statement['polygon'])) {
                    $mapFile = $this->mapService->createMapScreenshot($procedureId, $statement['ident']);
                }
                if (null !== $mapFile && 0 < strlen((string) $mapFile) && Statement::MAP_FILE_EMPTY_DASHED !== $mapFile) {
                    $fileInfo = $this->fileService->getFileInfoFromFileString($mapFile);
                    if (is_file($fileInfo->getAbsolutePath())) {
                        $fileContent = file_get_contents($fileInfo->getAbsolutePath());
                        $pictures['picture'.$i] = $fileInfo->getHash().'###'.$fileInfo->getFileName().'###'.base64_encode($fileContent);
                        ++$i;
                    }
                }
            } catch (Exception $e) {
                $this->logger->error('Could not generate Screenshot for Statement '.$statement['ident'].' ', [$e]);
            }
        }

        return $pictures;
    }

    /**
     * Returns a formatted date. Uses the 'authoredDate' entry in the array if existent or the 'submit' entry otherwise.
     *
     * @param $item array
     *
     * @return false|string
     */
    private function formatDate($item)
    {
        if (isset($item['authoredDate']) && 100000 < $item['authoredDate'] && 3 < strlen((string) $item['authoredDate'])) {
            // authored-dates apparently arrive in iso-format
            $date = $item['authoredDate'];
            $date = is_string($date) ? strtotime($date) : $date;
            $this->logger->debug('Found valid authoredDate: '.$date);
            $this->logger->debug('authoredDate (formatted): '.date('d.m.Y', $date));

            return date('d.m.Y', $date);
        } elseif (isset($item['submit'])) {
            $this->logger->debug('Use submitDate: '.$item['submit']);
            $this->logger->debug('submitDate (formatted): '.date('d.m.Y', $item['submit']));

            return date('d.m.Y', $item['submit']);
        }

        return false;
    }

    /**
     * @param array                                  $entityArrayList Array of Statement or Statement Fragment in array form (not object!) which has
     *                                                                the format that is needed here and the correct set of statements,
     *                                                                but in the wrong order
     * @param array<int|string, UuidEntityInterface> $orderedList     Array of Statement objects or Statement Fragment objects that may include
     *                                                                more than the selected ones, but which has the correct order
     */
    protected function reorderStatementsOrStatementFragmentsAccordingToOtherList(array $entityArrayList, array &$orderedList): array
    {
        // add keys to entityArray array
        $statementListWithKeysAsIds = [];
        foreach ($entityArrayList as $statement) {
            $statementListWithKeysAsIds[$statement['id']] = $statement;
        }

        // reorder
        $orderedStatements = [];
        /** @var Statement|StatementFragment $orderedListItem */
        foreach ($orderedList as $key => $orderedListItem) {
            // filter out only the required statements
            $orderedListItemId = $orderedListItem->getId();
            if (array_key_exists($orderedListItemId, $statementListWithKeysAsIds)) {
                $orderedStatements[] = $statementListWithKeysAsIds[$orderedListItemId];

                // removed used fragments for performance
                unset($orderedList[$key]);
            }
        }

        return $orderedStatements;
    }

    /**
     * @param array $statementFragments  array of Fragments in array form (not objects!)
     * @param array $selectedFragmentIds array of selected statement fragment ids. Empty array means "select all".
     */
    protected function filterForSelectedStatementFragments(array $statementFragments, array $selectedFragmentIds = []): array
    {
        if (0 < count($selectedFragmentIds)) {
            // filter if there are selected ids
            $unorderedList = [];
            foreach ($statementFragments as $fragment) {
                if (in_array($fragment['id'], $selectedFragmentIds, true)) {
                    $unorderedList[$fragment['id']] = $fragment;
                }
            }
        } else {
            // do not filter if there are no selected ids
            $unorderedList = $statementFragments;
        }

        return $unorderedList;
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

        $item = $this->assessmentTableOutput->formatStatementArray($statement);

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

    private function determineListLineWidth(string $template, string $templateName, bool $original): int
    {
        $listLineWidth = ListLineWidth::VERTICAL_SPLIT_VIEW->value;
        if ('portrait' === $template && 'export_original' === $templateName) {
            $listLineWidth = ListLineWidth::VERTICAL_NOT_SPLIT_VIEW->value;
        }
        if ($this->isHorizontalSplitView($template, $templateName, $original)) {
            $listLineWidth = ListLineWidth::HORIZONTAL_SPLIT_VIEW->value;
        }
        if ($this->isHorizontalNotSplitView($template, $templateName, $original)) {
            $listLineWidth = ListLineWidth::HORIZONTAL_NOT_SPLIT_VIEW->value;
        }

        return $listLineWidth;
    }

    private function determineTextLineWidth(string $template, string $templateName, bool $original): int
    {
        $textLineWidth = ListLineWidth::VERTICAL_SPLIT_VIEW->value;
        if ('portrait' === $template && 'export_original' === $templateName) {
            $textLineWidth = TextLineWidth::VERTICAL_NOT_SPLIT_VIEW->value;
        }
        if ($this->isHorizontalSplitView($template, $templateName, $original)) {
            $textLineWidth = TextLineWidth::HORIZONTAL_SPLIT_VIEW->value;
        }
        if ($this->isHorizontalNotSplitView($template, $templateName, $original)) {
            $textLineWidth = TextLineWidth::HORIZONTAL_NOT_SPLIT_VIEW->value;
        }

        return $textLineWidth;
    }

    private function isHorizontalSplitView(string $template, string $templateName, bool $original): bool
    {
        return ('landscape' === $template && 'export' === $templateName)
            || ('condensed' === $template && 'export_condensed' === $templateName && !$original)
            || ('condensed' === $template && 'export_condensed_anonymous' === $templateName && !$original)
            || ('landscape' === $template && 'export_anonymous' === $templateName && !$original)
            || ('landscapeWithFrags' === $template && 'export_fragments_anonymous' === $templateName && !$original);
    }

    private function isHorizontalNotSplitView(string $template, string $templateName, bool $original): bool
    {
        return ('landscape' === $template && 'export_original' === $templateName)
            || ('condensed' === $template && 'export_condensed' === $templateName && $original);
    }

    /**
     *  Note: Handling of `view_mode` could be implemented in the future.
     *   Currently, this can be ignored because the `view_mode` permission is only enabled in a specific project.
     *   And this project currently has no condensed exports.
     *
     *  Edit: It exists no `view_mode` permission. Instead, it exists 'feature_export_docx_elements_view_mode_only'
     *   (only enaled in the specifc project) and 'feature_assessmenttable_structural_view_mode'
     *   (commented out only in the specific project).
     */
    private function filterStatementsForCondensedExport(array $statements, string $template, string $exportType): array
    {
        if ('condensed' === $template && 'statementsOnly' !== $exportType) {
            $institutionStatements = collect($statements)
                ->filter(static fn (array $statement): bool => 'internal' === $statement['publicStatement'] && !$statement['isClusterStatement'])->values();

            $clusterStatements = collect($statements)
                ->filter(static fn (array $statement): bool => $statement['isClusterStatement'])->values();

            $publicStatements = collect($statements)
                ->filter(static fn (array $statement): bool => 'external' === $statement['publicStatement'] && !$statement['isClusterStatement'])->values();

            return $institutionStatements->merge($clusterStatements)
                ->merge($publicStatements)
                ->toArray();
        }

        return $statements;
    }

    /**
     * @throws AsynchronousStateException
     * @throws ReflectionException
     * @throws ErroneousDoctrineResult
     * @throws ProcedureNotFoundException
     */
    private function prepareStatementsForCondensedTemplate(
        array $statements,
        string $exportType,
        string $procedureId,
        bool $original,
        array $fragmentIds,
        bool $anonymous,
        ?Procedure $procedure,
    ): array {
        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }
        if ($procedure->getMaster()) {
            return [];
        }

        $filterHash = $this->session->get(
            'hashList'
        )[$procedureId]['assessment']['hash'];

        $items = $this->collectStatementsOrFragments(
            $statements,
            'statementsAndFragments' !== $exportType,
            $filterHash,
            $procedureId,
            $original,
            $fragmentIds
        );

        $items = $items->map(
            function ($item) use ($anonymous) {
                $formattedDate = $this->formatDate($item);
                if (false !== $formattedDate) {
                    $item['authoredDateDisplay'] = $formattedDate;
                }

                if (isset($item['cluster']) && is_array($item['cluster'])
                    && 0 < count($item['cluster'])) {
                    if (false === $anonymous) {
                        $departments = $this
                            ->assessmentTableOutput
                            ->collectClusterOrgaOutputForExport($item);
                        $item['clusteredInstitutions'] = $departments;
                    }
                    $item['metaDataOfClusteredStatements'] = $this
                        ->assessmentTableOutput
                        ->collectClusteredStatementMetaDataForExport($item);
                }

                return $item;
            }
        );

        return $items->toArray();
    }

    /**
     * Extracts statement and fragment IDs from the provided parameters.
     *
     * This function processes a provided array of items to extract only the IDs of statements. To ensure that only
     * statement IDs are considered, any identifiers are transformed into statement IDs. Additionally, fragment IDs are
     * stored separately to allow for further filtering and potential export of selected fragments.
     */
    private function extractStatementAndFragmentIds(array $parameters): IdCollection
    {
        $idCollection = new IdCollection();
        $fragmentIds = [];
        if (array_key_exists('items', $parameters) && 0 < (is_countable($parameters['items']) ? count($parameters['items']) : 0)) {
            $idArrays = [];
            foreach ($parameters['items'] as $statementOrFragmentId) {
                $idArray = $this->assessmentHandler
                    ->getStatementIdFromStatementIdOrStatementFragmentId(
                        $statementOrFragmentId
                    );
                $idArrays[] = $idArray['statementId'];
                if (null !== $idArray['fragmentId']) {
                    $fragmentIds[] = $idArray['fragmentId'];
                }
            }
            $idCollection->setStatementIds($idArrays);
        }
        $idCollection->setFragmentIds($fragmentIds);

        return $idCollection->lock();
    }

    /**
     * @throws Exception
     */
    private function createPdf(string $content, array $pictures, string $filenamePrefix, string $procedureName): array
    {
        $response = $this->serviceImport->exportPdfWithRabbitMQ(
            base64_encode($content),
            $pictures
        );
        $pdf['content'] = base64_decode($response);
        if ('' === $pdf['content']) {
            $this->logger->error('Exporting the assessment table as pdf failed.');
            throw new RuntimeException('No content for PDF');
        }
        $pdf['name'] = $filenamePrefix.'_'.$procedureName.'.pdf';
        $this->logger->debug(
            'Got Response: '.DemosPlanTools::varExport($pdf['content'], true)
        );
        $pdf['filename'] = $this->translator->trans('export').'.pdf';

        return $pdf;
    }
}
