<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
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
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Exception;
use LogicException;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tightenco\Collect\Support\Collection;
use Twig\Environment;

class AssessmentTablePdfExporter extends AssessmentTableFileExporterAbstract
{
    /** @var PermissionsInterface */
    private $permissions;
    /** @var Environment */
    protected $twig;
    /** @var ServiceImporter */
    protected $serviceImport;
    /** @var MapService */
    private $mapService;
    /** @var FileService */
    private $fileService;

    /** @var array */
    private $supportedTypes = ['pdf']; // : TODO: By Config ?

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    public function __construct(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentProcedureService $currentProcedureService,
        CurrentUserInterface $currentUser,
        Environment $twig,
        FileService $fileService,
        LoggerInterface $logger,
        MapService $mapService,
        PermissionsInterface $permissions,
        RequestStack $requestStack,
        ServiceImporter $serviceImport,
        StatementHandler $statementHandler,
        TranslatorInterface $translator
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
        $this->permissions = $permissions;
        $this->twig = $twig;
        $this->serviceImport = $serviceImport;
        $this->mapService = $mapService;
        $this->fileService = $fileService;
        $this->currentUser = $currentUser;
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function __invoke(array $parameters): array
    {
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
            $parameters = $this->addStatementsFromCurrentQueryHashToFilter($parameters, $procedureId, $original);
            $fragmentIds = [];
            if (array_key_exists('items', $parameters) && 0 < count($parameters['items'])) {
                $parameters['filters']['id'] = [];
                foreach ($parameters['items'] as $statementOrFragmentId) {
                    $idArray = $this->assessmentHandler
                        ->getStatementIdFromStatementIdOrStatementFragmentId(
                            $statementOrFragmentId
                        );
                    $parameters['filters']['id'][] = $idArray['statementId'];
                    if (null !== $idArray['fragmentId']) {
                        $fragmentIds[] = $idArray['fragmentId'];
                    }
                    unset($idArray);
                }
            }// get actual data for selected items
            // (at this point, only the id's are available)
            // Only the ids of statements are considered. To get around this issue, the ids were transformed to statement
            // ids in the lines above. Hence, now there are only ids of statements left.
            // However, to be able to export only selected fragments, the fragment ids are stored seperately and filtered
            // out later along the way.
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

            // here, handling view_mode could be implemented in the future.
            // We can ignore this at them moment, because view_mode permission is currently just enabled in bobhh
            // and they currently have no condensed exports
            if ('condensed' === $template && 'statementsOnly' !== $exportType) {
                $institutionStatements = collect($statements)
                    ->filter(static function (array $statement): bool {
                        return 'internal' === $statement['publicStatement'] && !$statement['isClusterStatement'];
                    })->values();

                $clusterStatements = collect($statements)
                    ->filter(static function (array $statement): bool {
                        return $statement['isClusterStatement'];
                    })->values();

                $publicStatements = collect($statements)
                    ->filter(static function (array $statement): bool {
                        return 'external' === $statement['publicStatement'] && !$statement['isClusterStatement'];
                    })->values();

                $statements = $institutionStatements->merge($clusterStatements)
                    ->merge($publicStatements)
                    ->toArray();
            }
            $statements = array_map(
                [$this->assessmentTableOutput, 'replacePhase'],
                $statements
            );
            if ('condensed' === $template) {
                if (null === $procedure) {
                    throw ProcedureNotFoundException::createFromId($procedureId);
                }
                if ($procedure->getMaster()) {
                    $statements = [];
                } else {
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
                        function ($item, $key) use ($anonymous) {
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
                    $statements = $items->toArray();
                }
            }
            $statements = $this->createExternIds($statements);
            $changedOutputResult['entries']['statements'] = $statements;
            $changedOutputResult['entries']['total'] = $outputResult->getTotal();

            $templateVars['table'] = $changedOutputResult;
            // T14612 filter house numbers depending of permission
            if (!$this->permissions->hasPermission('feature_statement_meta_house_number_export')) {
                foreach ($templateVars['table']['entries']['statements'] as $singleStatementData) {
                    $singleStatementData['meta']['houseNumber'] = '';
                }
            }// TODO: See whether we need this:
            //        // explicitly set procedure array, as it could not be fetched from session e.g. in export
            //        // if not set, passing only $procedureId is of no harm
            //        $procedure = $procedureId;
            //        try {
            //            $procedure = $this->getProcedureHandler()->getProcedure($procedureId);
            //        } catch (Exception $e) {
            //            // :-(
            //        }

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
            $content = $this->twig->render(
                $fullTemplateName,
                [
                    'templateVars' => $templateVars,
                    'isOriginal'   => $original,
                    'title'        => $title,
                    'procedure'    => $procedure,
                    'pdfLandscape' => 'landscape' === $template || 'landscapeWithFrags' === $template,
                    'viewMode'     => AssessmentTableViewMode::DEFAULT_VIEW,
                    'anonymous'    => $anonymous,
                ]
            );
            $procedureName = $this
                ->assessmentTableOutput
                ->selectProcedureName(
                    $procedure,
                    $this->currentUser->getUser()->isPublicUser()
                );
            $pictures = $this->collectPictures(
                $changedOutputResult['entries'],
                $procedureId
            ); // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
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
                if (0 < count($statement['fragments'])) {
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
                if (('' === $mapFile || null === $mapFile) && 0 < strlen($statement['polygon'])) {
                    $mapFile = $this->mapService->createMapScreenshot($procedureId, $statement['ident']);
                }
                if (null !== $mapFile && 0 < strlen($mapFile) && Statement::MAP_FILE_EMPTY_DASHED !== $mapFile) {
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
}
