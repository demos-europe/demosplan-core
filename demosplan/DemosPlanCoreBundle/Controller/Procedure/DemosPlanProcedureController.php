<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Form\Procedure\AbstractProcedureFormTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions as AttributeDplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Procedure\ProcedureEditedEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PublicDetailStatementListLoadedEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\CriticalConcernException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateSlugException;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRequiredException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\NoRecipientsWithEmailException;
use demosplan\DemosPlanCoreBundle\Exception\PreNewProcedureCreatedEventConcernException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Form\BoilerplateGroupType;
use demosplan\DemosPlanCoreBundle\Form\BoilerplateType;
use demosplan\DemosPlanCoreBundle\Form\ProcedureFormType;
use demosplan\DemosPlanCoreBundle\Form\ProcedureTemplateFormType;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\Document\DocumentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\Export\EntityPreparator;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Map\CoordinateJsonConverter;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\MessageSerializable;
use demosplan\DemosPlanCoreBundle\Logic\News\ProcedureNewsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureCategoryService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedurePhaseService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceOutput as ProcedureServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceStorage;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureCoupleTokenFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CountyService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\GdprConsentRevokeTokenService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\MultiTermsAggregation;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementSubmissionNotifier;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyService;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyShowHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\AddressBookEntryService;
use demosplan\DemosPlanCoreBundle\Logic\User\BrandingService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\MasterToebService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\EntitySyncLinkRepository;
use demosplan\DemosPlanCoreBundle\Repository\NotificationReceiverRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\Services\DatasheetService;
use demosplan\DemosPlanCoreBundle\Services\Map\GetFeatureInfo;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResultSet;
use demosplan\DemosPlanCoreBundle\ValueObject\MovedStatementData;
use demosplan\DemosPlanCoreBundle\ValueObject\PriorityPair;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\BoilerplateGroupVO;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\BoilerplateVO;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\ProcedureFormData;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Seitenausgabe Planverfahren.
 */
class DemosPlanProcedureController extends BaseController
{
    private const NONE = 'none';
    private const AGGREGATION_STATUS_PRIORITY = 'status_priority';

    /**
     * @var MapService
     */
    protected $mapService;

    /**
     * @var ProcedureService
     */
    protected $procedureService;

    /**
     * @var ProcedureServiceOutput
     */
    protected $procedureServiceOutput;

    public function __construct(
        private readonly AssessmentHandler $assessmentHandler,
        private readonly Environment $twig,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedureHandler $procedureHandler,
        ProcedureService $procedureService,
        ProcedureServiceOutput $procedureServiceOutput,
        private readonly ProcedureTypeResourceType $procedureTypeResourceType,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly CurrentProcedureService $currentProcedureService,
    ) {
        $this->procedureServiceOutput = $procedureServiceOutput;
        $this->procedureService = $procedureService;
    }

    /**
     * Verteiler für den Einstiegspunkt in das Verfahren.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @param Request                            $request      Unused
     * @param GlobalConfigInterface|GlobalConfig $globalConfig
     * @param string                             $procedure
     *
     * @return RedirectResponse
     */
    #[Route(name: 'DemosPlan_procedure_entrypoint', path: '/verfahren/{procedure}/entrypoint')]
    public function procedureEntrypointAction(Request $request, GlobalConfigInterface $globalConfig, $procedure)
    {
        $route = $globalConfig->getProcedureEntrypointRoute();

        return $this->redirectToRoute($route, ['procedure' => $procedure]);
    }

    /**
     * Redirect to a procedure by id.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @throws MessageBagException
     */
    #[Route(path: '/plan/{slug}', name: 'core_procedure_slug')]
    public function procedureSlugAction(
        CurrentUserInterface $currentUser,
        ProcedureServiceOutput $procedureOutput,
        string $slug = '',
    ): RedirectResponse|Response {
        try {
            $slugify = new Slugify();
            $slug = $slugify->slugify($slug);
            $procedure = $procedureOutput->getProcedureBySlug($slug, $currentUser->getUser());
            if (null === $procedure) {
                throw new NoResultException();
            }
            $redirectRoute = true === $currentUser->getUser()->isLoggedIn()
                ? $this->globalConfig->getProjectShortUrlRedirectRouteLoggedin()
                : $this->globalConfig->getProjectShortUrlRedirectRoute();

            return $this->redirectToRoute($redirectRoute, ['procedure' => $procedure->getId()]);
        } catch (NoResultException) {
            $this->getMessageBag()->add('error', 'warning.shorturl.no.procedure');

            return $this->redirectToRoute('core_home');
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Übersicht über das Verfahren.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/proceduredashboard/ Wiki: Verfahrensübersicht
     *
     * @DplanPermissions("area_admin_dashboard")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_dashboard', path: '/verfahren/{procedure}/uebersicht', options: ['expose' => true])]
    public function procedureDashboardAction(
        CurrentUserService $currentUserService,
        PermissionsInterface $permissions,
        StatementFragmentService $statementFragmentService,
        StatementService $statementService,
        SurveyService $surveyService,
        TranslatorInterface $translator,
        string $procedure,
    ) {
        $templateVars = [];
        $procedureId = $procedure;
        $procedureService = $this->procedureService;
        $procedureObject = $procedureService->getProcedure($procedureId);
        $templateVars['statementsTotal'] = 0;

        $templateVars = $this->collectProcedureDashboard($statementService, $translator, $permissions, $procedureId);

        try {
            if ($permissions->hasPermission('area_statements_fragment')) {
                $statementFragments = $statementFragmentService->getStatementFragmentsProcedure($procedureId);
                $fragmentStatusEmptyData = $this->getFragmentStatusEmptyData($translator);
                $fragmentVoteEmptyData = $this->getBucketEmptyData(
                    'statement_fragment_advice_values',
                    $translator
                );

                $templateVars['statementFragmentStatus'] = $this->extractAndPrepareDataFromBucket(
                    $statementFragments,
                    $translator,
                    'fragments_status',
                    $procedureId,
                    $fragmentStatusEmptyData
                );
                // planning agencies should see voteAdvices in dashboard
                $voteKey = 'voteAdvice';
                if ($this->permissions->hasPermission('feature_statements_fragment_vote')) {
                    $voteKey = 'vote';
                }
                $templateVars['statementFragmentVote'] = $this->extractAndPrepareDataFromBucket(
                    $statementFragments,
                    $translator,
                    $voteKey,
                    $procedureId,
                    $fragmentVoteEmptyData
                );
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not get StatementFragments by Status ', [$e]);
        }

        if ($this->permissions->hasPermission('area_statement_segmentation')) {
            $currentUser = $currentUserService->getUser();
            $templateVars['segmentableStatement'] = $statementService->getSegmentableStatement(
                $procedureId,
                $currentUser
            );
            $templateVars['segmentableStatementsCount'] = $statementService->getSegmentableStatementsCount(
                $procedureId,
                $currentUser
            );
            $templateVars['statementsSegmentedByUser'] = $statementService->getSegmentedStatements(
                $procedureId,
                $currentUser
            );
        }

        if ($this->permissions->hasPermission('area_survey')) {
            $templateVars['surveys'] = $procedureObject->getSurveys();
            $templateVars['surveyStatistics'] = $surveyService->generateSurveyStatistics($procedureObject);
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_dashboard.html.twig',
            [
                'procedure'    => $procedureId,
                'templateVars' => $templateVars,
                'title'        => 'dashboard.index',
            ]
        );
    }

    /**
     * Collect Data for Statement priority / status chart
     * Data needs to be in following structure
     *  $templateVars['statementPriorities'] = [
     *       ['key' => 'none', 'label' => 'Nicht vergeben', 'color' => '#D9D7EF'],
     *       ['key' => 'prio_a', 'label' => 'Prio A', 'color' => '#807dba'],
     *       ['key' => 'prio_b', 'label' => 'Prio B', 'color' => '#e08214'],
     *   ];.
     *
     *   $templateVars['statementStatusData'] = [
     *       ['Category' => 'Neu', 'count' => 20, 'freq' =>
     *           [
     *               'none' => 17,
     *               'prio_a' => 2,
     *               'prio_b' => 1
     *           ]
     *       ],
     *       ['Category' => 'In Bearbeitung', 'count' => 5, 'freq' =>
     *           [
     *               'none' => 3,
     *               'prio_a' => 2,
     *               'prio_b' => 1
     *           ]
     *       ],
     *   ];
     *
     * @return array{statementPriorities?: list<PriorityPair>, statementStatusData?: list<array{Category: string, count: int, freq: array<string, int>, url?: string}>, movedStatementData?: MovedStatementData, procedureHasSurveys?: bool}
     *
     * @throws Exception
     */
    protected function collectProcedureDashboard(
        StatementService $statementService,
        TranslatorInterface $translator,
        PermissionsInterface $permissions,
        string $procedureId,
    ): array {
        $templateVars = [];
        /** @var array<string, string> $formParamsStatementStatus */
        $formParamsStatementStatus = $this->getFormParameter('statement_status');
        /** @var array<string, string> $formParamsStatementPriority */
        $formParamsStatementPriority = $this->getFormParameter('statement_priority');
        // add Empty Value
        $formParamsStatementPriority[''] = 'notassigned';

        // precollect data for each priority
        foreach ($formParamsStatementPriority as $key => $label) {
            $templateVars['statementPriorities'][] = new PriorityPair(
                $this->generateCategoryKey($key),
                $translator->trans($label),
            );
        }

        $statementStatusData = $this->getStatementStatusData(
            $permissions,
            $translator,
            $formParamsStatementStatus,
            $formParamsStatementPriority,
            $statementService,
            $procedureId
        );
        if (null !== $statementStatusData) {
            $templateVars['statementStatusData'] = $statementStatusData['statementStatusData'];
            $templateVars['statementsTotal'] = $statementStatusData['total'];
        }

        // try block is about getting count for moved statements
        try {
            $procedure = $this->procedureService->getProcedure($procedureId);
            $movedStatementData = $statementService->getMovedStatementData($procedure);
            if (null !== $movedStatementData) {
                $templateVars['movedStatementData'] = $movedStatementData;
            }
            $templateVars['procedureHasSurveys'] = count($procedure->getSurveys()) > 0;
        } catch (Exception $e) {
            $this->getLogger()->error('Failed to get moved procedures for dashboard', [$e]);
        }

        return $templateVars;
    }

    protected function generateCategoryKey(string $key): string
    {
        return '' === $key ? self::NONE : $key;
    }

    /**
     * @param array<string, string> $statementStatuses
     * @param array<string, string> $statementPriorities
     *
     * @return array{statementStatusData: list<array{Category: string, count: int, freq: array<string, int>, url?: string}>, total: int}|null
     *
     * @throws Exception
     */
    protected function getStatementStatusData(
        PermissionsInterface $permissions,
        TranslatorInterface $translator,
        array $statementStatuses,
        array $statementPriorities,
        StatementService $statementService,
        string $procedureId,
    ): ?array {
        $priorityInitialValues = [];
        foreach ($statementPriorities as $key => $label) {
            // save initial zero values to be set later on as defaults
            $priorityInitialValues[$this->generateCategoryKey($key)] = 0;
        }

        // collect for each status their priority aggregations
        // therefore several Elasticsearch requests needs to be fired
        // Permission 'area_statement_segmentation' is required for the "Splitting statement box" in the dashboard.
        if (!$permissions->hasPermissions(['feature_statements_statistic_state_and_priority', 'area_statement_segmentation'], 'OR')) {
            return null;
        }

        $statementStatusData = [];
        // generate a statementStatusData item for each statement status
        foreach ($statementStatuses as $statusKey => $statusLabel) {
            // set default data if no aggregation is found
            $statementStatusData[$statusKey] = [
                'Category' => $translator->trans($statusLabel),
                'count'    => 0,
                'freq'     => $priorityInitialValues,
            ];
        }

        try {
            $statementQueryResult = $this->getStatements($statementService, $procedureId);
        } catch (Exception $exception) {
            $this->logger->warning('Could not get Statements by Status ', [$exception]);

            return [
                'statementStatusData' => array_values($statementStatusData),
                'total'               => 0,
            ];
        }

        // save status counts
        $aggregations = $statementQueryResult->getFilterSet()['filters'];

        // Check if the status aggregation exists
        if (isset($aggregations[StatementService::AGGREGATION_STATEMENT_STATUS])) {
            foreach ($aggregations[StatementService::AGGREGATION_STATEMENT_STATUS] as $aggregationBucket) {
                if (array_key_exists($aggregationBucket['value'], $statementStatuses)) {
                    $statusValue = $aggregationBucket['value'];
                    $statusCount = $aggregationBucket['count'];
                    $statementStatusData[$statusValue]['count'] = $statusCount;

                    // add link with filterhash to assessment table
                    if (0 < $statusCount) {
                        $statementStatusData[$statusValue]['url'] = $this->generateAssessmentTableFilterLinkFromStatus(
                            $statusValue,
                            $procedureId,
                            'statement'
                        );
                    }
                }
            }
        }

        if (isset($aggregations[self::AGGREGATION_STATUS_PRIORITY])) {
            foreach ($aggregations[self::AGGREGATION_STATUS_PRIORITY] as $aggregationBucket) {
                [$statusValue, $priorityValue] = $aggregationBucket['value'];
                if ('' == $priorityValue || 'no_value' === $priorityValue) {
                    $priorityValue = self::NONE;
                }
                $statementStatusData[$statusValue]['freq'][$priorityValue] = $aggregationBucket['count'];
            }
        }

        return [
            'statementStatusData' => array_values($statementStatusData),
            'total'               => $statementQueryResult->getTotal(),
        ];
    }

    /**
     * Takes the status attribute and creates filterhash-links.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/filterhash/ Wiki: Filterhashes
     *
     * @param string $type 'statement' or 'fragment'
     *
     * @throws Exception
     */
    public function generateAssessmentTableFilterLinkFromStatus(
        string $statusLabel,
        string $procedureId,
        string $type,
    ): string {
        $filterArray = [];
        // First, the status is transformed into the right format for the following method.
        if ('fragment' === $type) {
            // @improve T12957
            $filterArray = ['filter_fragments_status' => [$statusLabel]];
        } elseif ('statement' === $type) {
            // @improve T12957
            $filterArray = ['filter_status' => [$statusLabel]];
        }

        return $this->assessmentHandler->generateAssessmentTableFilterLink($procedureId, $filterArray);
    }

    /**
     * @param TranslatorInterface $translator
     * @param string              $filterKey
     * @param string              $procedureId
     * @param array               $data
     *
     * @return array
     */
    protected function extractAndPrepareDataFromBucket(ElasticsearchResultSet $statements, $translator, $filterKey, $procedureId, $data = [])
    {
        $dataCollection = \collect($data);
        // save priorities per status
        if (\array_key_exists($filterKey, $statements->getFilterSet()['filters'])) {
            foreach ($statements->getFilterSet()['filters'][$filterKey] as $aggregation) {
                $dataCollection->transform(function ($dataItem) use ($aggregation, $translator, $filterKey, $procedureId) {
                    if ($dataItem['key'] === $translator->trans($aggregation['label'])) {
                        $dataItem['value'] = $aggregation['count'];

                        // generate url to assessment table for fragments filtered by status
                        if ('fragments_status' === $filterKey) {
                            $dataItem['url'] = $this->generateAssessmentTableFilterLinkFromStatus(
                                $aggregation['label'],
                                $procedureId,
                                'fragment'
                            );
                        }
                    }

                    return $dataItem;
                });
            }
        }

        return $dataCollection->toArray();
    }

    /**
     * get empty values from Elasticsearch Buckets.
     *
     * @param string              $formOptionsKey
     * @param TranslatorInterface $translator
     *
     * @return array
     */
    protected function getBucketEmptyData($formOptionsKey, $translator)
    {
        $data = [];
        $params = $this->getFormParameter($formOptionsKey);
        $data[] = [
            'key'   => 'Keine Zuordnung',
            'value' => 0,
        ];
        foreach ($params as $paramLabel) {
            $data[] = [
                'key'   => $translator->trans($paramLabel),
                'value' => 0,
            ];
        }

        return $data;
    }

    /**
     * Create initial Values for fragment status.
     *
     * @param TranslatorInterface $translator
     *
     * @return array
     */
    protected function getFragmentStatusEmptyData($translator)
    {
        $fragmentStatus = $this->getFormParameter('fragment_status') ?? [];

        $data = [];
        foreach ($fragmentStatus as $paramLabel) {
            $data[] = [
                'key'   => $translator->trans($paramLabel),
                'value' => 0,
            ];
        }

        return $data;
    }

    /**
     * @param string $action
     */
    protected function prepareIncomingData(Request $request, $action): array
    {
        $result = [];

        $incomingFields = [
            'new'             => [
                'action',
                'r_copymaster',
                'r_customerMasterBlueprint',
                'r_desc',
                'r_externalDesc',
                'r_enddate',
                'r_master',
                'r_mapExtent',
                'r_name',
                'r_plisId',
                'r_procedure_type',
                'r_publicParticipationContact',
                'r_startdate',
                'uploadedFiles',
                'procedureCoupleToken',
            ],
            'delete'          => [
                'action',
                'procedure_delete',
            ],
            'adminlist'       => [
                'action',
                'filter_phase',
            ],
            'emailEdit'       => [
                'action',
                'orga_selected',
                'r_emailCc',
                'r_emailText',
                'r_emailTitle',
            ],
            'edit'            => [
                'action',
                'delete_logo',
                'fieldCompletions',
                'r_agency',
                'r_ars',
                'r_authorizedUsers',
                'r_autoSwitch',
                'r_autoSwitchPublic',
                'r_coordinate',
                'r_currentPublicParticipationPhase',
                'r_customerMasterBlueprint',
                'r_dataInputOrga',
                'r_deletePictogram',
                'r_desc',
                'r_designatedSwitchDate',
                'r_designatedEndDate',
                'r_designatedPhase',
                'r_designatedPublicEndDate',
                'r_designatedPublicPhase',
                'r_designatedPublicSwitchDate',
                'r_enddate',
                'r_externalDesc',
                'r_externalName',
                'r_phase_iteration',
                'r_public_participation_phase_iteration',
                'r_ident',
                'r_legalNotice',
                'r_links',
                'r_locationName',
                'r_locationPostCode',
                'r_municipalCode',
                'r_name',
                'r_oldSlug',
                'r_phase',
                'r_pictogram',
                'r_pictogramCopyright',
                'r_pictogramAltText',
                'r_procedure_categories',
                'r_publicParticipation',
                'r_publicParticipationContact',
                'r_publicParticipationEndDate',
                'r_publicParticipationPhase',
                'r_publicParticipationPublicationEnabled',
                'r_publicParticipationStartDate',
                'r_sendMailsToCounties',
                'r_shortUrl',
                'r_startdate',
                'r_export_settings',
            ],
            'newSuscription'  => [
                'action',
                'r_postalcode',
                'r_radius',
            ],
            'boilerplateedit' => [
                'action',
                'r_boilerplateCategory',
                'r_text',
                'r_title',
            ],
        ];

        $request = $request->request->all();

        foreach ($incomingFields[$action] as $key) {
            if (\array_key_exists($key, $request)) {
                $result[$key] = $request[$key];

                // Only handle receivers if r_sendMailsToCounties is checked
                if ('r_sendMailsToCounties' === $key && 'on' === $request[$key]
                    && \array_key_exists('r_receiver', $request)) {
                    $result['r_receiver'] = $request['r_receiver'];
                }
            }
        }

        return $result;
    }

    /**
     * Creates a new procedure (not a procedure template, use
     * {@link DemosPlanProcedureController::newProcedureTemplateAction()} for that).
     *
     * @DplanPermissions("feature_admin_new_procedure")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_new', path: '/verfahren/neu', options: ['expose' => true])]
    public function newProcedureAction(
        Breadcrumb $breadcrumb,
        CurrentUserInterface $currentUser,
        FormFactoryInterface $formFactory,
        MasterTemplateService $masterTemplateService,
        Request $request,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator,
    ) {
        $templateVars = [];
        // Reichere die breadcrumb mit zusätzl. items an
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('procedure.admin.list', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_procedure_administration_get'),
            ]
        );

        $templateVars['breadcrumb'] = $breadcrumb;
        $templateVars = $this->procedureService->setPlisInTemplateVars($templateVars);

        // Formulardaten verarbeiten
        $inData = $this->prepareIncomingData($request, 'new');

        $form = $this->getForm(
            $formFactory,
            new ProcedureFormData(),
            ProcedureFormType::class,
            true,
            true
        );
        // add new data from request to form
        $form->handleRequest($request);

        if (\array_key_exists('action', $inData) && 'new' === $inData['action']
            && $form->isSubmitted()
            && $form->isValid()) {
            $inData = $this->procedureService->fillInData($inData, $form);

            $logErrorMessage = 'Failed to create new procedure';

            try {
                $procedure = $serviceStorage->administrationNewHandler($inData, $currentUser->getUser()->getId());

                $this->messageBag->addObject(
                    MessageSerializable::createMessage(
                        'confirm',
                        'confirm.procedure.created',
                        ['name' => $procedure->getName()]
                    ),
                    true
                );

                return $this->redirectToRoute('DemosPlan_procedure_edit', ['procedure' => $procedure->getId()]);
            } catch (CriticalConcernException $criticalConcernException) {
                foreach ($criticalConcernException->getConcerns() as $pluginIdentifier => $concerns) {
                    foreach ($concerns as $concern) {
                        $this->logger->error('Error in '.$pluginIdentifier, [$concern->getException()]);
                        $this->messageBag->add('error', $concern->getMessage());
                    }
                }
            } catch (PreNewProcedureCreatedEventConcernException $e) {
                $this->logger->error("$logErrorMessage due to event concerns", [$e]);
                foreach ($e->getMessages() as $message) {
                    $this->messageBag->add('error', $message);
                }
            } catch (ViolationsException $e) {
                $this->logger->error("$logErrorMessage due to validation violations", [$e]);
                foreach ($e->getViolationsAsStrings() as $violationMessage) {
                    $this->messageBag->add('error', $violationMessage);
                }
            } catch (Exception $e) {
                $this->logger->error($logErrorMessage, [$e]);
                $this->messageBag->add('error', 'error.procedure.create');
            }
        }
        $this->writeErrorsIntoMessageBag($form->getErrors(true));

        $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp('procedure.new');
        $templateVars = $this->addProcedureTypesToTemplateVars($templateVars, false);
        $templateVars = $this->procedureServiceOutput->fillTemplateVars($templateVars);
        $templateVars['masterTemplateId'] = $masterTemplateService->getMasterTemplateId();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_new.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $translator->trans('procedure.new', [], 'page-title'),
                'form'         => $form->createView(),
            ]
        );
    }

    /**
     * @DplanPermissions("area_admin_procedure_templates")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_master_new', path: '/verfahren/blaupausen/neu', options: ['expose' => true])]
    public function newProcedureTemplateAction(
        Breadcrumb $breadcrumb,
        CurrentUserInterface $currentUser,
        FormFactoryInterface $formFactory,
        MasterTemplateService $masterTemplateService,
        Request $request,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator,
    ) {
        $templateVars = [];
        // Reichere die breadcrumb mit zusätzl. items an
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('procedure.master.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_procedure_templates_list'),
            ]
        );

        $templateVars['breadcrumb'] = $breadcrumb;
        $templateVars = $this->procedureService->setPlisInTemplateVars($templateVars);

        // Formulardaten verarbeiten
        $inData = $this->prepareIncomingData($request, 'new');

        $form = $this->getForm(
            $formFactory,
            new ProcedureFormData(),
            ProcedureTemplateFormType::class,
            true,
            true
        );
        // add new data from request to form
        $form->handleRequest($request);

        // skip email form validation if it is a blueprint because agencyMainEmailAddress is only mandatory when creating procedures
        // For blueprints/templates the agencyMainEmailAddress should be set to empty string
        if (\array_key_exists('action', $inData) && 'new' === $inData['action']) {
            $inData = $this->procedureService->fillInData($inData, $form);

            $logErrorMessage = 'Failed to create new procedure template';

            try {
                $procedure = $serviceStorage->administrationNewHandler($inData, $currentUser->getUser()->getId());

                $this->messageBag->addObject(
                    MessageSerializable::createMessage(
                        'confirm',
                        'confirm.procedure_template.created',
                        ['name' => $procedure->getName()]
                    ),
                    true
                );

                return $this->redirectToRoute('DemosPlan_procedure_edit_master', ['procedure' => $procedure->getId()]);
            } catch (CriticalConcernException $criticalConcernException) {
                foreach ($criticalConcernException->getConcerns() as $pluginIdentifier => $concerns) {
                    foreach ($concerns as $concern) {
                        $this->logger->error('Error in '.$pluginIdentifier, [$concern->getException()]);
                        $this->messageBag->add('error', $concern->getMessage());
                    }
                }
            } catch (PreNewProcedureCreatedEventConcernException $e) {
                $this->logger->error("$logErrorMessage due to event concerns", [$e]);
                foreach ($e->getMessages() as $message) {
                    $this->messageBag->add('error', $message);
                }
            } catch (ViolationsException $e) {
                $this->logger->error("$logErrorMessage due to validation violations", [$e]);
                foreach ($e->getViolationsAsStrings() as $violationMessage) {
                    $this->messageBag->add('error', $violationMessage);
                }
            } catch (Exception $e) {
                $this->logger->error($logErrorMessage, [$e]);
                $this->messageBag->add('error', 'error.procedure_template.create');
            }
        }
        $this->writeErrorsIntoMessageBag($form->getErrors(true));

        $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp('procedure.master.new');
        $templateVars = $this->addProcedureTypesToTemplateVars($templateVars, true);
        $templateVars = $this->procedureServiceOutput->fillTemplateVars($templateVars);
        $templateVars['masterTemplateId'] = $masterTemplateService->getMasterTemplateId();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_new_master.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $translator->trans('procedure.master.new', [], 'page-title'),
                'form'         => $form->createView(),
            ]
        );
    }

    /**
     * TöB hinzufügen Liste.
     *
     * @DplanPermissions({"area_main_procedures","area_admin_invitable_institution"})
     *
     * @param string $procedure
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_member_add_mastertoeblist', path: '/verfahren/{procedure}/einstellungen/benutzer/hinzufuegen/mastertoeblist', options: ['expose' => true])]
    public function administrationNewMemberListMastertoeblistAction(
        CurrentProcedureService $currentProcedureService,
        MasterToebService $masterToebService,
        Request $request,
        ServiceStorage $serviceStorage,
        $procedure,
    ) {
        // Storage initialisieren
        $requestPost = $request->request->all();

        if (\array_key_exists('orga_add', $requestPost)) {
            $addorgas = $requestPost['orga_add'];
            $storageResult = $serviceStorage->addOrgaToProcedureHandler($procedure, $addorgas);

            if (true === $storageResult) {
                $this->getMessageBag()->add('confirm', 'confirm.invitable_institutions.added');

                return new RedirectResponse(
                    $this->generateUrl(
                        'DemosPlan_procedure_member_index',
                        ['procedure' => $procedure]
                    )
                );
            }

            $this->getMessageBag()->add('warning', 'warning.invitable_institution.not.added');
        }

        $templateVars = [];
        // Template Variable aus Storage Ergebnis erstellen(Output)
        $masterToebOrgas = $masterToebService->getMasterToebs(true);
        $procedureOrgaIds = $currentProcedureService->getProcedureWithCertainty()->getOrganisationIds();

        // Stelle nur die Orgas dar, die noch nicht zugewiesen sind
        foreach ($masterToebOrgas as $masterToebOrga) {
            if (!\in_array($masterToebOrga['oId'], $procedureOrgaIds, true)) {
                $templateVars['orgas'][] = $masterToebOrga;
            }
        }

        $template = '@DemosPlanCore/DemosPlanProcedure/administration_new_member_list_mastertoeblist.html.twig';

        return $this->renderTemplate(
            $template,
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.public.agency.add',
                'procedure'    => $procedure,
            ]
        );
    }

    /**
     * Email to invite unregistered public agencies.
     *
     * @DplanPermissions("area_invite_unregistered_public_agencies")
     *
     * @param string $procedureId
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_invite_unregistered_public_agency_email', path: '/verfahren/{procedureId}/einstellungen/unregistrierte_toeb_email')]
    public function administrationUnregisteredPublicAgencyEMailAction(
        AddressBookEntryService $addressBookEntryService,
        Request $request,
        TranslatorInterface $translator,
        $procedureId,
    ): Response {
        $procedureService = $this->procedureService;
        $procedure = $procedureService->getProcedure($procedureId);

        $requestPost = $request->request->all();
        $selectedAddressBookEntries = [];
        $emailText = $translator->trans(
            'text.invite.unregistered.recipient',
            ['organisationName' => $procedure->getOrgaName(), 'organisationEmail' => $procedure->getAgencyMainEmailAddress()]
        );

        // on load email edit view:
        if (\array_key_exists('writeEmail', $requestPost) && \array_key_exists('entries_selected', $requestPost)) {
            $selectedAddressBookEntries = $addressBookEntryService->getAddressBookEntries($requestPost['entries_selected']);
        }

        // on click on send email:
        if (\array_key_exists('sendEmail', $requestPost) && \array_key_exists('entries_selected', $requestPost)) {
            $addressBookEntryEmails = [];
            $selectedAddressBookEntries = $addressBookEntryService->getAddressBookEntries($requestPost['entries_selected']);
            foreach ($selectedAddressBookEntries as $addressBookEntry) {
                $addressBookEntryEmails[] = $addressBookEntry->getEmailAddress();
            }

            $this->procedureHandler->sendMailToAddresses($procedure, $addressBookEntryEmails, $requestPost);

            $this->getMessageBag()->add(
                'confirm',
                'confirm.register.invitation.sent',
                ['emailAddressen' => implode(', ', $addressBookEntryEmails)]
            );

            return new RedirectResponse(
                $this->generateUrl(
                    'DemosPlan_invite_unregistered_public_agency_email',
                    [
                        'procedureId' => $procedure->getId(),
                    ]
                )
            );
        }

        $templateVars = [
            'procedure'          => $procedure,
            'addressBookEntries' => $selectedAddressBookEntries,
            'emailCC'            => $procedure->getSettings()->getEmailCc(),
            'emailText'          => $emailText,
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_unregistered_publicagency_email.html.twig',
            [
                'templateVars' => $templateVars,
                'procedureId'  => $procedureId,
                'procedure'    => $procedureId,
                'title'        => 'email.invitation.write',
            ]
        );
    }

    /**
     * List of unregistered public agencies, which will be filled by address book of organisations.
     *
     * @DplanPermissions("area_invite_unregistered_public_agencies")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_invite_unregistered_public_agency_list', path: '/verfahren/{procedureId}/einstellungen/unregistrierte_toeb_liste')]
    public function administrationUnregisteredPublicAgencyListAction(
        AddressBookEntryService $addressBookEntryService,
        CurrentUserService $currentUserService,
        Request $request,
        string $procedureId,
    ): Response {
        $orgaId = $currentUserService->getUser()->getOrganisationId() ?? '';

        $procedureService = $this->procedureService;
        $procedure = $procedureService->getProcedure($procedureId);

        $requestPost = $request->request->all();

        if (\array_key_exists('unregirstered_toeb_add', $requestPost)) {
            $addressBookEntryIdsToAdd = $requestPost['unregirstered_toeb_add'];
            $procedureService->addAddressBookEntryToProcedure($procedure, $addressBookEntryIdsToAdd);

            // todo: avoid reload resend request
        }

        $addressBookEntriesOfOrganisation = $addressBookEntryService->getAddressBookEntriesOfOrganisation($orgaId);

        $templateVars = [
            'procedure'          => $procedure,
            'addressBookEntries' => $addressBookEntriesOfOrganisation,
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_unregistered_publicagency_list.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => 'invitable_institution.unregistered.invite',
            ]
        );
    }

    /**
     * Administrate the E-Mail to send to invited and registered toeb/public agencies/members.
     *
     * @DplanPermissions("area_main_procedures","area_admin_invitable_institution")
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Throwable
     */
    #[Route(name: 'DemosPlan_admin_member_email', path: '/verfahren/{procedureId}/einstellungen/mitglieder_email', options: ['expose' => true])]
    public function administrationMemberEMailAction(
        OrgaService $orgaService,
        Request $request,
        string $procedureId,
        string $title = 'email.invitation.write',
    ): Response {
        $requestPost = $request->request->all();
        $selectedOrganisations = [];
        if (\array_key_exists('email_orga_action', $requestPost) && \array_key_exists('orga_selected', $requestPost)) {
            $selectedOrganisations = $orgaService->getOrganisationsByIds($requestPost['orga_selected']);
        }

        $procedureService = $this->procedureService;
        $procedure = $procedureService->getProcedure($procedureId);
        $boilerplates = $procedureService->getBoilerplatesOfCategory($procedureId, 'email');

        $emailTextToAdd = '';
        if ($this->permissions->hasPermission('feature_email_invitable_institution_additional_invitation_text')) {
            $emailTextToAdd = $this->generateAdditionalEmailText($procedure, $selectedOrganisations);
        }

        $templateVars = [
            'procedure'      => $procedure,
            'boilerplates'   => $boilerplates,
            'emailTextAdded' => $emailTextToAdd,
            'emailTextToAdd' => $emailTextToAdd,
            'orga_selected'  => $selectedOrganisations,
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_member_email.html.twig',
            [
                'templateVars' => $templateVars,
                'procedureId'  => $procedureId,
                'procedure'    => $procedureId, // procedureId in procedure is needed for menü highlighting
                'title'        => $title,
            ]
        );
    }

    /**
     * Helper method to creates an defined text to attach to an Email.
     *
     * @throws Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function generateAdditionalEmailText(Procedure $procedure, array $selectedOrganisations): string
    {
        // todo: move this method in service?

        //        refs T6189: Only toeb, who were selected to recive the invitation mail are listed in mail-body.
        $organization = $procedure->getOrga();
        if (0 === count($selectedOrganisations)) {
            $procedureServiceOutput = $this->procedureServiceOutput;
            $toutputResult = $procedureServiceOutput->procedureMemberListHandler($procedure->getId(), null);
            $selectedOrganisations = $toutputResult['orgas'];
        }

        return $this->generateAdditionalInvitationEmailText($selectedOrganisations, $organization, $procedure->getAgencyMainEmailAddress());
    }

    /**
     * @param Orga $organization the organization to use as entity
     *
     * @throws Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function generateAdditionalInvitationEmailText(array $publicAffairsAgents, $organization, string $procedureMainEmail): string
    {
        $context = [
            'templateVars' => [
                'toebliste'          => $publicAffairsAgents,
                'projectName'        => $this->globalConfig->getProjectName(),
                'organisation'       => $organization,
                'procedureMainEmail' => $procedureMainEmail,
            ],
            'gatewayURL'             => $this->globalConfig->getGatewayURL(),
        ];

        return $this->twig
            ->load('@DemosPlanCore/DemosPlanProcedure/administration_send_invitation_email.html.twig')
            ->renderBlock('body_plain', $context);
    }

    /**
     * Allgemeine Einstellungen eines Verfahrens.
     *
     * @DplanPermissions({"area_main_procedures", "area_admin_preferences"})
     *
     * @param bool $isMaster Ist es eine Blaupause?
     *
     * @return RedirectResponse|Response
     *
     * @throws CustomerNotFoundException
     * @throws MessageBagException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    #[Route(name: 'DemosPlan_procedure_edit', path: '/verfahren/{procedure}/einstellungen', defaults: ['isMaster' => false])]
    #[Route(name: 'DemosPlan_procedure_edit_master', path: '/verfahren/blaupause/{procedure}/einstellungen', defaults: ['isMaster' => true])]
    public function administrationEditAction(
        ContentService $contentService,
        CurrentUserService $currentUser,
        CurrentProcedureService $currentProcedureService,
        CustomerService $customerService,
        EntityManagerInterface $em,
        EntityPreparator $entityPreparator,
        EntitySyncLinkRepository $entitySyncLinkRepository,
        EventDispatcherPostInterface $eventDispatcherPost,
        FileUploadService $fileUploadService,
        FormFactoryInterface $formFactory,
        MailService $mailService,
        ProcedureCategoryService $procedureCategoryService,
        ProcedureCoupleTokenFetcher $coupleTokenService,
        Request $request,
        ServiceStorage $serviceStorage,
        StatementService $statementService,
        TranslatorInterface $translator,
        string $procedure,
        bool $isMaster = false,
    ) {
        try {
            $procedureId = $procedure;

            // Storage und Output initialisieren
            $procedureServiceOutput = $this->procedureServiceOutput;
            $procedureService = $this->procedureService;

            $procedureObject = $procedureService->getProcedureWithCertainty($procedureId);

            // Formulardaten verarbeiten
            $inData = $this->prepareIncomingData($request, 'edit');
            $inData['r_pictogram'] = $fileUploadService->prepareFilesUpload($request, 'r_pictogram', true);

            // wappen_logo / r_logo is not used anywhere and ui for uploading/editing it has been removed from dplan.
            // However, it is still found in backend - @TODO cleanup controllers as well
            $inData['r_logo'] = $fileUploadService->prepareFilesUpload($request, 'wappen_logo', true);

            // Ist-Zustand des Verfahrens
            $currentProcedure = $currentProcedureService->getProcedureArray();

            // In case the following form handling looks strange or out of place please bear in mind
            // that this is one of the first tests to use symfony forms with validation without using
            // it for all fields in the given request.
            // Basically additionally to the previously existing fields two new fields for email data
            // are handled by a form bypassing the original request handling till the
            // $serviceStorage->administrationEditHandler($inData) function.

            // create form from current procedure data
            $form = $this->getForm(
                $formFactory,
                new ProcedureFormData($procedureObject),
                $isMaster ? ProcedureTemplateFormType::class : ProcedureFormType::class,
                true,
                true
            );

            // add new data from request to form
            $form->handleRequest($request);

            if (\array_key_exists('action', $inData)
                && 'edit' === $inData['action']) {
                // ensure that form component triggers even when no field is used
                // that is defined in Form component
                if (!$form->isSubmitted()) {
                    // manually trigger submit as we know that form has been submitted
                    $form->submit($request->request->all());
                }

                if ($form->isValid()) {
                    /** @var ProcedureFormData $procedureFormData */
                    $procedureFormData = $form->getData();
                    $inData[AbstractProcedureFormTypeInterface::AGENCY_MAIN_EMAIL_ADDRESS] = $procedureFormData->getAgencyMainEmailAddressFullString();
                    $inData[AbstractProcedureFormTypeInterface::AGENCY_EXTRA_EMAIL_ADDRESSES] = $procedureFormData->getAgencyExtraEmailAddressesFullStrings();
                    $inData[AbstractProcedureFormTypeInterface::ALLOWED_SEGMENT_ACCESS_PROCEDURE_IDS] = $procedureFormData->getAllowedSegmentAccessProcedureIds();
                }

                $this->validateAdministrationEditInput($inData);

                // If there is no institution participation, only the externalName aka. publicly visible name
                // can be edited. To prevent both values to be displayed (which is the default behavior when
                // values differ) the fields are synced.
                if (false === $this->permissions->hasPermission('feature_institution_participation')
                    && true === $this->permissions->hasPermission('area_public_participation')
                    && isset($inData['r_externalName'])) {
                    $inData['r_name'] = $inData['r_externalName'];
                }

                // also change the procedureSettings EmailTitle as it may contain the old ProcedureName
                // used for email subjects when inviting registered institutions
                $emailTitle = $procedureObject->getSettings()->getEmailTitle();
                if ($procedureObject->getName() !== $inData['r_name']
                    // the user may have updated the emailTitle already and in this case do not update
                    && !$this->eMailTitleContainsNewProcedureName($emailTitle, $inData['r_name'])
                    && $this->eMailTitleContainsOldProcedureName($emailTitle, $procedureObject->getName())
                ) {
                    $inData['r_emailTitle'] = str_ireplace(
                        $procedureObject->getName(),
                        (string) $inData['r_name'],
                        $procedureObject->getSettings()->getEmailTitle());
                }

                // Storage Formulardaten übergeben
                $storageResult = $serviceStorage->administrationEditHandler($inData);

                $event = new ProcedureEditedEvent(
                    $procedureId,
                    $currentProcedure,
                    $inData,
                    $currentUser->getUser()
                );
                $eventDispatcherPost->post($event);

                // Template Variable aus Storage Ergebnis erstellen(Output)
                $procedureAsArray = $procedureServiceOutput->getProcedureWithPhaseNames($procedureId);

                // generiere eine Erfolgsmeldung
                if (false !== $storageResult && !\array_key_exists('mandatoryfieldwarning', $storageResult)) {
                    $this->getMessageBag()->add('confirm', 'confirm.saved');

                    // Prüfe, ob eine Email an die Verfahrensabonnenten geschicht werden soll
                    $publicParticipationPhase = $procedureAsArray['publicParticipationPhase'];
                    if (isset($inData['r_currentPublicParticipationPhase']) && $publicParticipationPhase !== $inData['r_currentPublicParticipationPhase']) {
                        $externalPhasesAssoc = $this->globalConfig->getExternalPhasesAssoc();
                        if (isset($externalPhasesAssoc[$publicParticipationPhase]) && 'write' === $externalPhasesAssoc[$publicParticipationPhase]['permissionset']) {
                            // Schicke die Email an die Interessenten
                            $this->sendProcedureSubscriptionEmail($mailService, $translator, $procedureAsArray);
                        }
                    }

                    return $this->redirectBack($request);
                }
            }
            $this->writeErrorsIntoMessageBag($form->getErrors(true));

            // Template Variable aus Storage Ergebnis erstellen(Output)
            $procedureAsArray = $procedureServiceOutput->getProcedureWithPhaseNames($procedureId);
            // Only procedures have editable export setting
            if (!$isMaster) {
                if (null === $procedureAsArray['procedureType']) {
                    $this->logger->warning('Procedure has no ProcedureType:', ['procedureId' => $procedureId]);
                }
                /** @var Procedure $currentProcedureObject */
                $currentProcedureObject = $currentProcedure['settings']['procedure'];
                $procedureAsArray['statementFieldDefinitions'] = [];
                if (null !== $currentProcedureObject->getStatementFormDefinition()) {
                    $procedureAsArray['statementFieldDefinitions'] = $currentProcedureObject->getStatementFormDefinition()->getFieldDefinitions()->toArray();
                }
                $procedureAsArray['exportSettings'] = $entityPreparator->convert($currentProcedureObject->getDefaultExportFieldsConfiguration());
            }

            $settings = $contentService->getSettingsByProcedureId($procedureId);
            $procedureAsArray = \array_merge($procedureAsArray, $settings);
            // Wandle für die Ausgabe <br> zu Newlines zurück
            $procedureAsArray['externalDesc'] = \preg_replace(
                '|<br />|s',
                "\n",
                (string) $procedureAsArray['externalDesc']
            );
            $templateVars = ['proceduresettings' => $procedureAsArray];

            $agencies = $procedureServiceOutput->getPlanningOffices($customerService->getCurrentCustomer());
            $templateVars['agencies'] = $agencies;

            $dataInputOrgas = $procedureServiceOutput->getDataInputOrgas();
            $templateVars['dataInputOrgas'] = $dataInputOrgas;
            // get current shortUrlPath
            $templateVars['shortUrlPath'] = $this->generateUrl(
                'core_procedure_slug',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $templateVars['inData']['r_shortUrl'] = $procedureAsArray['currentSlug']->getName();

            // Verfahrensschritte
            $templateVars['internalPhases'] = $this->globalConfig->getInternalPhases();
            $templateVars['externalPhases'] = $this->globalConfig->getExternalPhases();

            // ProcedureCategories
            $templateVars['procedureCategories'] = $procedureCategoryService->getProcedureCategories();

            // Template auswählen
            if (true === $isMaster) {
                $template = '@DemosPlanCore/DemosPlanProcedure/administration_edit_master.html.twig';
                $title = 'procedure.master.adjustments';
            } else {
                $template = '@DemosPlanCore/DemosPlanProcedure/administration_edit.html.twig';
                $title = 'procedure.adjustments';
            }
            /** @var NotificationReceiverRepository $notificationReveicerRepository */
            $notificationReveicerRepository = $em->getRepository(NotificationReceiver::class);

            $templateVars['notificationReceivers'] = $notificationReveicerRepository->getNotificationReceiversByProcedure($procedure);

            $templateVars['procedure'] = $procedureObject;

            // Get list of authorized users without current user - sorted after Users lastname (a to z)
            if ($this->globalConfig->hasProcedureUserRestrictedAccess()) {
                $templateVars['authorizedUsers'] = $procedureService->getAuthorizedUsers(
                    $procedureId,
                    null,
                    true,
                    false
                )->sort(static function (User $userA, User $userB): int {
                    $lastNameCmpResult = strcmp((string) $userA->getLastname(), (string) $userB->getLastname());
                    if (0 === $lastNameCmpResult) {
                        return strcmp((string) $userA->getName(), (string) $userB->getName());
                    }

                    return $lastNameCmpResult;
                });
            }

            $templateVars['isCustomerMasterBlueprint'] = $procedureObject->isCustomerMasterBlueprint();
            $templateVars['isCustomerMasterBlueprintExisting'] =
                $procedureService->isCustomerMasterBlueprintExisting($customerService->getCurrentCustomer()->getId());

            $templateVars['sourceProcedureCoupleToken'] = $coupleTokenService->getTokenForSourceProcedure($procedureObject);
            $templateVars['targetProcedureCoupleToken'] = $coupleTokenService->getTokenForTargetProcedure($procedureObject);
            $templateVars['sourceProcedure'] = $coupleTokenService->getSourceProcedureFromTokenByTargetProcedure($procedureObject);
            $templateVars['targetProcedure'] = $coupleTokenService->getTargetProcedureFromTokenBySourceProcedure($procedureObject);
            $templateVars['statementCount'] = $statementService->getStatementResourcesCount($procedureId);
            $templateVars['synchronizedStatementCount'] = $entitySyncLinkRepository->getSynchronizedStatementCount($procedureId);

            return $this->renderTemplate(
                $template,
                [
                    'templateVars' => $templateVars,
                    'procedure'    => $procedureId,
                    'title'        => $title,
                    'form'         => $form->createView(),
                ]
            );
        } catch (DuplicateSlugException $e) {
            $this->getMessageBag()->add('error', 'error.procedure.duplicated.shorturl', ['slug' => $e->getDuplicatedSlug()]);

            return $this->redirectToRoute('DemosPlan_procedure_edit', ['procedure' => $procedureId]);
        } catch (ViolationsException $e) {
            $this->logger->error("Failed to edit procedure or procedure template with ID '$procedureId' due to constraint violations.", [$e]);

            return $this->redirectToRoute('DemosPlan_procedure_edit', ['procedure' => $procedureId]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error("Failed to edit procedure or procedure template with ID '$procedureId'.", [$e]);

            return $this->redirectToRoute('DemosPlan_procedure_edit', ['procedure' => $procedureId]);
        }
    }

    /**
     * @DplanPermissions({"area_main_procedures","area_admin_preferences"})
     *
     * @param string $procedure
     *
     * @return JsonResponse
     */
    #[Route(name: 'DemosPlan_procedure_edit_ajax', path: '/verfahren/{procedure}/einstellungen/update', options: ['expose' => true])]
    public function administrationEditAjaxAction(
        CurrentProcedureService $currentProcedureService,
        CurrentUserService $currentUser,
        EventDispatcherPostInterface $eventDispatcherPost,
        FileUploadService $fileUploadService,
        Request $request,
        ServiceStorage $serviceStorage,
        $procedure,
    ) {
        $currentProcedure = $currentProcedureService->getProcedureArray();
        $storageResult = [];

        $incomingData = $this->prepareIncomingData($request, 'edit');
        $incomingData['r_pictogram'] = $fileUploadService->prepareFilesUpload($request, 'r_pictogram', true);
        if (\array_key_exists('action', $incomingData) && 'edit' === $incomingData['action']) {
            $storageResult = $serviceStorage->administrationEditHandler($incomingData, false);

            $event = new ProcedureEditedEvent(
                $procedure,
                $currentProcedure,
                $incomingData,
                $currentUser->getUser()
            );
            $eventDispatcherPost->post($event);
        }

        return $this->renderJson($storageResult);
    }

    /**
     * Starting point for importing items into a procedure.
     *
     * @DplanPermissions({"area_main_procedures", "area_admin_import"})
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_import', path: '/verfahren/{procedureId}/import', options: ['expose' => true])]
    public function administrationImportAction(
        CurrentUserService $currentUser,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        StatementService $statementService,
        string $procedureId,
    ): Response {
        $currentUserId = $currentUser->getUser()->getId();
        $templateVars = [
            'newestInternalId' => $statementService->getNewestInternId($procedureId),
            'usedInternIds'    => $statementService->getInternIdsFromProcedure($procedureId),
        ];

        if ($permissions->hasPermission('feature_statements_tag')) {
            $templateVars['availableTopics'] = $procedureService->getTopics($procedureId);
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_import.html.twig',
            [
                'currentUserId' => $currentUserId,
                'procedureId'   => $procedureId,
                'title'         => 'import',
                'templateVars'  => $templateVars,
            ]
        );
    }

    /**
     * Verschicke Emails an alle, deren Umkreissuche auf das aktuelle Verfahren passt.
     *
     * @param TranslatorInterface $translator
     * @param array               $procedure
     */
    protected function sendProcedureSubscriptionEmail(MailService $mailService, $translator, $procedure)
    {
        $vars = [];
        if (!isset($procedure['locationPostCode']) || '' === $procedure['locationPostCode']) {
            // @todo im Service abfangen
            return;
        }

        try {
            // Gibt es User, die benachrichtigt werden wollen?
            $usersToSendMail = $this->procedureHandler->getProcedureSubscriptionList(
                $procedure['ident'],
                $this->globalConfig->getUseOpenGeoDb()
            );

            if (isset($usersToSendMail) && $usersToSendMail['total'] > 0) {
                // Setze die Mailvariablen
                $mailTemplateVars = $procedure;
                $mailTemplateVars['projectName'] = $this->globalConfig->getProjectName();
                $vars['mailsubject'] = $translator->trans('email.subject.procedure.subscription');
                $vars['mailbody'] = $this->twig
                    ->load(
                        '@DemosPlanCore/DemosPlanProcedure/subscriptions_email.html.twig'
                    )
                    ->renderBlock(
                        'body_plain',
                        ['templateVars' => $mailTemplateVars]
                    );

                // schicke jedem User eine Email (einmal, auch bei mehreren Alertmatches)
                $emailSent = [];
                /** @var ProcedureSubscription $subscription */
                foreach ($usersToSendMail['result'] as $subscription) {
                    if (\in_array($subscription->getUserEmail(), $emailSent)) {
                        continue;
                    }

                    // Schicke die Mail. Das Template dm_subscription schleift Body & Text 1:1 durch
                    $mailService->sendMail(
                        'dm_subscription',
                        'de_DE',
                        $subscription->getUserEmail(),
                        '',
                        '',
                        '',
                        'extern',
                        $vars
                    );
                    $emailSent[] = $subscription->getUserEmail();
                }
            }
            // fange Mailversandexceptions hier ab, damit sie den sonstigen Workflow nicht stören
        } catch (Throwable $e) { // renderBlock may throw Throwables
            $this->logger->error('Ein Fehler ist beim Mailversand aufgetreten: ', [$e]);
        }
    }

    /**
     * öffentliche Verfahrensdetailseite.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @return RedirectResponse|Response
     *
     * @throws Throwable
     */
    #[Route(name: 'DemosPlan_procedure_public_detail', path: '/verfahren/{procedure}/public/detail', options: ['expose' => true])]
    public function publicDetailAction(
        BrandingService $brandingService,
        ContentService $contentService,
        CountyService $countyService,
        CurrentUserInterface $currentUser,
        CurrentProcedureService $currentProcedureService,
        DatasheetService $datasheetService,
        DocumentHandler $documentHandler,
        DraftStatementHandler $draftStatementHandler,
        DraftStatementService $draftStatementService,
        ElementsService $elementsService,
        EventDispatcherPostInterface $eventDispatcherPost,
        FileUploadService $fileUploadService,
        GdprConsentRevokeTokenService $gdprConsentRevokeTokenService,
        GetFeatureInfo $getFeatureInfo,
        MapService $mapService,
        ParagraphService $paragraphService,
        PermissionsInterface $permissions,
        ProcedureNewsService $procedureNewsService,
        ProcedurePhaseService $procedurePhaseService,
        Request $request,
        StatementHandler $statementHandler,
        StatementService $statementService,
        SurveyShowHandler $surveyShowHandler,
        StatementSubmissionNotifier $statementSubmissionNotifier,
        CoordinateJsonConverter $coordinateJsonConverter,
        string $procedure,
    ) {
        // @improve T14613
        $procedureId = $procedure;
        unset($procedure);

        $subdomain = $this->globalConfig->getSubdomain();
        if (!$this->procedureHandler->isProcedureInCustomer($procedureId, $subdomain)) {
            return $this->redirectToRoute('core_home');
        }

        // logged in users should see their procedure startpage if participation area is not used
        if (true === $currentUser->getUser()->isLoggedIn() && false === $this->permissions->hasPermission('area_combined_participation_area')) {
            return $this->redirectToRoute(
                $this->globalConfig->getProcedureEntrypointRoute(),
                ['procedure' => $procedureId]
            );
        }

        // check procedure permissions
        try {
            $this->canAccessProcedure();
        } catch (AccessDeniedException $e) {
            $this->logger->error('This user '.$currentUser->getUser()->getId().' should not access procedure '.$procedureId, [$e]);

            return $this->redirectToRoute('core_home');
        }

        $templateVars = [
            'isSubmitted'    => false,
            'procedureLayer' => 'participation',
        ];
        $requestPost = $request->request->all();

        if (\array_key_exists('action', $requestPost) && 'statementpublicnew' === $requestPost['action']) {
            try {
                $this->permissions->checkPermission('feature_new_statement');

                if (true === $currentUser->getUser()->isLoggedIn()) {
                    // Formulardaten einsammeln
                    $requestPost['r_uploaddocument'] = $fileUploadService->prepareFilesUpload($request, 'r_file');

                    // Storage Formulardaten übergeben
                    $serviceStorage = $draftStatementHandler;
                    $draftStatement = $serviceStorage->newHandler($procedureId, $requestPost);

                    $templateVars['draftStatementIdent'] = $draftStatement['id'];
                    $templateVars['number'] = $draftStatement['number'];
                } else {
                    $event = new RequestValidationWeakEvent(
                        $request,
                        null,
                        'publicStatement'
                    );
                    try {
                        $eventDispatcherPost->post($event);
                    } catch (Exception $e) {
                        return $this->redirectToRoute('core_home');
                    }

                    $statementHandler->setRequestValues($requestPost);
                    try {
                        $savedStatement = $statementHandler->savePublicStatement($procedureId);
                        $templateVars['confirmationText'] =
                            $statementHandler->getPresentableStatementSubmitConfirmationText(
                                $savedStatement->getExternId(),
                                $currentProcedureService->getProcedureWithCertainty()
                            );
                    } catch (GdprConsentRequiredException) {
                        $this->getMessageBag()->add('warning', 'warning.gdpr.consent');

                        return $this->redirectToRoute('DemosPlan_procedure_public_detail', ['procedureId' => $procedureId]);
                    }

                    $templateVars['draftStatementIdent'] = $savedStatement->getDraftStatementId();
                    $templateVars['number'] = $savedStatement->getExternId();
                }

                // wenn keine Exception aufgetreten ist, ist alles in Ordnung
                $templateVars['isSubmitted'] = true;

                // Wenn der User eine E-Mail-Adresse angegeben hat, schicke eine Bestätigungsmail
                if ($request->request->has('r_email') && 0 < \strlen((string) $requestPost['r_email'])) {
                    $fullEmailAddress = $requestPost['r_email'];
                    $gdprConsentRevokeToken = isset($savedStatement)
                        ? $gdprConsentRevokeTokenService->maybeCreateGdprConsentRevokeToken(
                            $fullEmailAddress,
                            $savedStatement->getOriginal()
                        )
                        : null;
                    $statementSubmissionNotifier->sendEmailOnNewStatement(
                        $requestPost['r_text'],
                        $fullEmailAddress,
                        null,
                        $templateVars['number'],
                        $gdprConsentRevokeToken
                    );
                    // Benachrichtige das Template, dass ein Emailversand erwünscht war
                    $templateVars['wantsEmail'] = true;
                }
            } catch (ValidatorException) {
                // Werte ins Template übergeben
                $templateVars['request'] = $requestPost;
            } catch (ViolationsException $e) {
                foreach ($e->getViolationsAsStrings() as $violationsAsString) {
                    $this->getMessageBag()->add('error', $violationsAsString);
                }
            }
        }

        $currentProcedure = $currentProcedureService->getProcedureArray();
        $templateVars['procedureSettings'] = $currentProcedure['settings'];
        $templateVars['procedureSettings']['territory'] = $coordinateJsonConverter->convertJsonToCoordinates($currentProcedure['settings']['territory']);
        // Globale Sachdatenabfrage hinzufügen
        $templateVars['procedureSettings']['featureInfoUrl'] = $getFeatureInfo->getUrl();

        $settings = $contentService->getSettings(
            'layerGroupsAlternateVisibility',
            SettingsFilter::whereProcedureId($procedureId)->lock(),
            false
        );

        $layerGroupsAlternateVisibility = (1 === count((array) $settings)) ? $settings[0]->getContent() : false;
        if (false === \is_bool($layerGroupsAlternateVisibility)) {
            $layerGroupsAlternateVisibility = \filter_var($layerGroupsAlternateVisibility, \FILTER_VALIDATE_BOOLEAN);
        }
        $templateVars['procedureSettings']['layerGroupsAlternateVisibility'] = $layerGroupsAlternateVisibility;

        $templateVars['mapOptions'] = $mapService->getMapOptions($procedureId);

        $baseLayers = $mapService->getGisList($procedureId, 'base');
        $templateVars['baselayers'] = [
            'gislayerlist' => $mapService->getLayerObjects($baseLayers),
        ];
        $overlayLayers = $mapService->getGisList($procedureId, 'overlay');
        $templateVars['overlays'] = [
            'gislayerlist' => $mapService->getLayerObjects($overlayLayers),
        ];
        $templateVars['availableProjections'] = $this->globalConfig->getMapAvailableProjections();

        $templateVars['counties'] = $countyService->getCounties();

        $manualSortScope = 'procedure:'.$procedureId;
        $user = $currentUser->getUser();
        $resultList = $procedureNewsService->getNewsList($procedureId, $user, $manualSortScope, null, $user->getRoles());
        $templateVars['newsList'] = $resultList['result'];

        // Soll das Stellungnahmeformular angezeigt werden?
        $templateVars['displayStatementForm'] = false;
        if ($this->permissions instanceof Permissions && $this->permissions->hasPermissionsetWrite(Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL)) {
            $templateVars['displayStatementForm'] = true;

            //  get form options for statement form
            $templateVars['formOptions']['userGroup'] = $this->getFormParameter('statement_user_group');
            $templateVars['formOptions']['userPosition'] = $this->getFormParameter('statement_user_position');
            $templateVars['formOptions']['userState'] = $this->getFormParameter('statement_user_state');
        }

        // Wie viele Öffentliche Stellungnahmen sind vorhanden?
        $filters = ['publicVerified' => Statement::PUBLICATION_APPROVED];
        // Öffentliche Stellungnahmen
        $publicLimit = $request->get('r_limit', 10);
        $publicPage = $request->get('page', 1);
        $statementService->setPaginatorLimits([10]);
        $statements = $statementService->getStatementsByProcedureId(
            $procedureId,
            $filters,
            ToBy::createArray('submitDate', 'desc'),
            '',
            $publicLimit,
            $publicPage
        );

        // add information about liked public statements
        $statementCollection = \collect($statements->getResult());
        $event = new PublicDetailStatementListLoadedEvent(
            $statementCollection,
            $request,
            $user
        );
        $eventDispatcherPost->post($event);

        // get Updated Statementlist from Event
        $templateVars['publicStatements']['statements'] = $event->getStatements()->toArray();
        // plugin may add a list of statements liked by user
        $templateVars['publicStatements']['likedStatementIds'] = $event->getLikedStatementIds()->toArray();
        $templateVars['publicStatements']['pager'] = $statements->getPager();

        $templateVars['publicStatements']['totalResults'] = $statements->getTotal();
        $templateVars['publicStatements']['limitResults'] = $publicLimit;

        // Soll eine Stellungnahme zu einem paragraph abgegeben werden?
        if ($request->query->has('r_paragraphID')) {
            $templateVars['statement']['paragraphId'] = $request->query->get('r_paragraphID');
            $queryParagraph = $paragraphService->getParaDocument(
                $request->query->get('r_paragraphID')
            );
            // bei bestehenden Stellungnahmen ist es die ParagraphVersion
            if (null === $queryParagraph) {
                $queryParagraph = $paragraphService->getParaDocumentVersion(
                    $request->query->get('r_paragraphID')
                );
                $templateVars['statement']['paragraphId'] = '';
            }
            $templateVars['statement']['paragraphTitle'] = $queryParagraph['title'] ?? '';
        }
        if ($request->query->has('r_elementID')) {
            $templateVars['statement']['elementId'] = $request->query->get('r_elementID');
            $queryElement = $elementsService->getElement($request->query->get('r_elementID'));
            $templateVars['statement']['elementTitle'] = $queryElement['title'] ?? '';
        }

        // Wurden Planungsdokumente hochgeladen?
        $templateVars['planningDocuments'] = $documentHandler->hasProcedureElements($procedureId, $user->getOrganisationId());

        // is the negative statement plannindocument category enabled?
        $templateVars['planningDocumentsHasNegativeStatement'] =
            $elementsService->hasNegativeReportElement($procedureId);

        // fill linkbox if set
        if ('' !== $currentProcedure['settings']['links']) {
            $templateVars['linkbox'] = $currentProcedure['settings']['links'];
        }

        // procedure categories
        $templateVars['procedureCategories'] = $currentProcedure['procedureCategories'];

        // Soll eine bestehende Stellungnahme editiert werden?
        if (true === $currentUser->getUser()->isLoggedIn() && $request->query->has('draftStatementId')) {
            try {
                $draftStatement = $draftStatementService->getDraftStatement(
                    $request->query->get('draftStatementId')
                );

                // nur eigene Drafts dürfen bearbeitet werden
                // oder wenn user über das recht verfügt + angehöriger der orga ist

                if ($user->getId() == $draftStatement['uId']
                    || ($this->permissions->hasPermission('feature_statements_released_group_edit')
                    && $user->getOrganisationId() == $draftStatement['oId'])) {
                    $templateVars['draftStatement'] = $draftStatement;
                    $templateVars['request']['r_text'] = $draftStatement['text'];
                    // Id des Kreises, dem die SN zugeordnet ist
                    $county = null;
                    if (!\array_key_exists('noLocation', $draftStatement['statementAttributes'])) {
                        $dsCounty = $draftStatement['statementAttributes']['county'];
                        $county = $countyService->findCountyByName($dsCounty);
                    }

                    if (null !== $county) {
                        $templateVars['draftStatement']['statementAttributes']['county'] = $county[0]->getId();
                    }
                } else {
                    $this->getLogger()->warning(
                        'User '.$user->getId().' '.$user->getFullname().
                        ' hat versucht, unberechtigt auf DraftStatement '.$draftStatement['ident'].' zuzugreifen'
                    );
                }
            } catch (Exception $e) {
                $this->getLogger()->warning(
                    'DraftStatement could not be found',
                    [
                        'id' => $request->query->get('r_draftStatementId'),
                        $e,
                    ]
                );
            }
        }

        // Count tabs - refs T6116
        $tabCount = 0;
        if ($currentProcedure['isMapEnabled'] && $this->permissions->hasPermission('area_map_participation_area')) {
            ++$tabCount;
        }
        if (\array_key_exists('planningDocuments', $templateVars) && $templateVars['planningDocuments']) {
            ++$tabCount;
        }
        if (
            $this->permissions->hasPermission('area_statements_public_published_public')
            && \array_key_exists('publicStatements', $templateVars)
            && \array_key_exists('statements', $templateVars['publicStatements'])
            && 0 < sizeof($templateVars['publicStatements']['statements'])
        ) {
            ++$tabCount;

            // Add file containers to check for published attachments in statement tab
            foreach ($templateVars['publicStatements']['statements'] as $key => $iValue) {
                $id = $templateVars['publicStatements']['statements'][$key]['id'];
                $templateVars['publicStatements']['statements'][$key]['fileContainers'] = $statementService->getFileContainersForStatement($id);

                // improve: use an event to enrich the data with additional data from addons
            }
        }
        // T16602 display html datasheets only in Procedures "wind" Version 1 and 2
        $templateVars['htmlAvailable'] = \in_array($datasheetService->getDatasheetVersion($procedureId), [1, 2], true);

        // orga Branding
        if ($this->permissions->hasPermission('area_orga_display')) {
            $orgaBranding = $brandingService->createOrgaBrandingFromProcedureId($procedureId);
            $templateVars['orgaBranding'] = $orgaBranding;
        }

        $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);

        // Survey Info
        if ($this->permissions->hasPermission('area_survey')) {
            $survey = $procedure->getFirstSurvey();
            $templateVars['survey'] = $surveyShowHandler->entityToFrontend(
                $survey,
                $user
            );
        }

        // Is autorisation via token available in current procedure
        if ($permissions->hasPermission('feature_public_consultation')) {
            $templateVars['isPublicConsultationPhase'] = $procedurePhaseService->isPublicConsultationPhase($procedure);
        }

        $templateVars['procedureUiDefinition'] = $procedure->getProcedureUiDefinition();
        $templateVars['statementFormDefinition'] = $procedure->getStatementFormDefinition();
        $templateVars['statementFieldDefinitions'] = [];
        if (null !== $procedure->getStatementFormDefinition()) {
            $templateVars['statementFieldDefinitions'] = $procedure->getStatementFormDefinition()->getFieldDefinitions()->toArray();
        }
        $templateVars['fallbackStatementReplyUrl'] = $this->globalConfig->getFallbackStatementReplyUrl(); // move this into event?

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/public_detail.html.twig',
            [
                'tabCount'     => $tabCount,
                'procedure'    => $procedureId,
                'title'        => 'procedure',
                'templateVars' => $templateVars,
            ]
        );
    }

    /**
     * Display Procedures to user where orga is allowed to input new statements.
     *
     * @DplanPermissions("area_statement_data_input_orga")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_list_data_input_orga_procedures', path: '/verfahren/datainput/list')]
    public function dataInputOrgaChooseProcedureAction(CurrentUserService $currentUser): Response
    {
        $templateVars = [];
        $organisationId = $currentUser->getUser()->getOrganisationId();
        $templateVars['allowedProcedures'] = null === $organisationId
            ? []
            : $this->procedureService->getProceduresForDataInputOrga($organisationId);
        $title = 'procedure.admin.list';

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/list_data_input_orga_procedures.html.twig',
            \compact('templateVars', 'title')
        );
    }

    /**
     * Verwalte die Abonnements/Benachrichtigunsgservices für eine Region.
     *
     * @DplanPermissions("area_subscriptions")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_list_subscriptions', path: '/verfahren/abonnieren', options: ['expose' => true])]
    public function subscribeAction(CurrentUserService $currentUser, Request $request)
    {
        $templateVars = [];
        $requestPost = $request->request->all();

        // Überprüfe, ob PLZ und Orte für Benachrichtigungen eingegeben wurden
        if (\array_key_exists('newSubscription', $requestPost)) {
            $postalCode = $requestPost['r_postalCode'];
            if (5 != \strlen((string) $postalCode)) {
                /* In BOB-SH's area_subscriptions feature the postal code is sent by the FE only if
                 * one of the dropdown entries is selected, otherwise (eg. even when a valid postal
                 * code is entered into the search field) `r_postalCode` will be sent by the FE
                 * containing an empty string. Not adding a subscription in this case is correct,
                 * and while the resulting error message is not perfect it is considered okayish.
                 */
                $this->getMessageBag()->add('error', 'explanation.postalcode.not.valid');
            } else {
                $city = $requestPost['r_city'] ?? '';
                $distance = $requestPost['r_radius'] ?? 0;
                $this->procedureHandler->addSubscription(
                    $postalCode,
                    $city,
                    $distance,
                    $currentUser->getUser()
                );

                return $this->redirectToRoute('DemosPlan_procedure_list_subscriptions');
            }
        }

        // delete notification if required
        if (\array_key_exists('deleteSubscription', $requestPost)) {
            if (!isset($requestPost['region_selected']) || 0 === (is_countable($requestPost['region_selected']) ? count($requestPost['region_selected']) : 0)) {
                $this->getMessageBag()->add('error', 'warning.select.entries');
            } else {
                $deleteNotifications = $requestPost['region_selected'];
                $actuallyDeletedCount = $this->procedureHandler->deleteSubscriptions($deleteNotifications);
                if ((is_countable($deleteNotifications) ? count($deleteNotifications) : 0) === $actuallyDeletedCount) {
                    $this->getMessageBag()->add('confirm', 'confirm.entries.marked.deleted');
                }

                return $this->redirectToRoute('DemosPlan_procedure_list_subscriptions');
            }
        }
        // gebe eventuelle Abonnements für Benachrichtungen aus
        $subscriptions = $this->procedureHandler->getSubscriptionList($currentUser->getUser()->getId());
        if (0 < $subscriptions['total']) {
            $templateVars['subscriptions'] = $subscriptions;
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/list_subscriptions.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.notifications',
            ]
        );
    }

    // @improve: T15117
    // @improve: T15850
    /**
     * @DplanPermissions("area_admin_invitable_institution")
     *
     * @param string $procedure
     *
     * @return RedirectResponse|Response
     *
     * @throws Throwable
     */
    #[Route(name: 'DemosPlan_procedure_member_index', path: '/verfahren/{procedure}/einstellungen/benutzer', options: ['expose' => true])]
    public function administrationMemberListAction(
        Breadcrumb $breadcrumb,
        MailService $mailService,
        OrgaService $orgaService,
        Request $request,
        StatementService $statementService,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator,
        $procedure,
    ) {
        // Storage und Output initialisieren
        $serviceOutput = $this->procedureServiceOutput;
        $requestPost = $request->request->all();
        $title = 'procedure.public.agency.administration';

        // todo: this is a workaround to get selected organisations into administrationMemberEMailAction()
        if (\array_key_exists('email_orga_action', $requestPost)) {
            return $this->administrationMemberEMailAction($orgaService, $request, $procedure, $title);
        }

        if (\array_key_exists('search_word', $requestPost)) {
            $search = $requestPost['search_word'];
        } else {
            $search = null;
        }

        if (\array_key_exists('delete_orga_action', $requestPost)) {
            if (!isset($requestPost['orga_selected']) || 0 === (is_countable($requestPost['orga_selected']) ? count($requestPost['orga_selected']) : 0)) {
                $this->getMessageBag()->add('error', 'explanation.invitable_institutions.noneselected');
            } else {
                $deleteorga = $requestPost['orga_selected'];
                $storageResult = $serviceStorage->detachOrganisationsFromProcedure($procedure, $deleteorga);
                // generiere eine Erfolgsmeldung
                if (true === $storageResult) {
                    $this->getMessageBag()->add('confirm', 'confirm.invitable_institutions.deleted');

                    return new RedirectResponse(
                        $this->generateUrl('DemosPlan_procedure_member_index', ['procedure' => $procedure])
                    );
                }
            }
        }

        if (\array_key_exists('updateEmailText', $requestPost)) {
            $inData = $this->prepareIncomingData($request, 'emailEdit');
            $inData['action'] = 'updateEmailText';
            $storageResult = $serviceStorage->updateEmailTextHandler($inData, $procedure);

            // generiere eine Erfolgsmeldung
            if (false !== $storageResult && !\array_key_exists('mandatoryfieldwarning', $storageResult)) {
                $this->getMessageBag()->add(
                    'confirm',
                    $translator->trans(
                        'confirm.invitation.saved',
                        ['variable' => '']
                    )
                );

                return new RedirectResponse(
                    $this->generateUrl('DemosPlan_procedure_member_index', ['procedure' => $procedure])
                );
            }
        }

        // Bereite den automatisch angehängten Emailtext vor

        // besorge dir die Liste der TöBs für die E-Mail-Adressen
        $templateVars = $serviceOutput->procedureMemberListHandler($procedure, null);
        $publicAffairsAgents = $templateVars['orgas'];
        if (isset($requestPost['orga_selected'])) {
            $publicAffairsAgents = $orgaService->getOrganisationsByIds($requestPost['orga_selected']);
        }

        $procedureAsArray = $serviceOutput->getProcedureWithPhaseNames($procedure);
        $organization = $orgaService->getOrga($procedureAsArray['orga']->getId());

        $emailTextAdded = '';
        if ($this->permissions->hasPermission('feature_email_invitable_institution_additional_invitation_text')) {
            $emailTextAdded = $this->generateAdditionalInvitationEmailText($publicAffairsAgents, $organization, $procedureAsArray['agencyMainEmailAddress'] ?? '');
        }
        // versende die Einladungsemail mit dem aktuell eingegebenen Text und speichere den Text nicht
        if (\array_key_exists('sendInvitationEmail', $requestPost)) {
            if (!isset($requestPost['orga_selected']) || 0 === (is_countable($requestPost['orga_selected']) ? count($requestPost['orga_selected']) : 0)) {
                $this->getMessageBag()->add(
                    'error',
                    'explanation.invitable_institutions.noneselected',
                    ['variable' => '']
                );

                return $this->redirectBack($request);
            } else {
                $helperServices = [
                    'serviceMail'      => $mailService,
                    'serviceDemosPlan' => $statementService,
                ];
                $this->procedureHandler->setHelperServices($helperServices);

                // verfasse und verschicke die Einladungs-E-Mail
                try {
                    $storageResult = $this->procedureHandler->sendInvitationEmails(
                        $procedureAsArray,
                        $this->prepareIncomingData($request, 'emailEdit')
                    );

                    // generiere eine Erfolgsmeldung für die eingeladenen TöB
                    $this->getMessageBag()->add(
                        'confirm',
                        'confirm.invitation.sent',
                        ['variable' => implode(', ', $storageResult->getOrgasInvited())]
                    );
                    // generiere eine Fehlermeldung für die nicht eingeladenen TöB
                    if ([] !== $storageResult->getOrgasNotInvited()) {
                        $this->getMessageBag()->add(
                            'error',
                            'error.email.invitation.send.no.email',
                            ['variable' => implode(', ', $storageResult->getOrgasNotInvited())]
                        );
                    }

                    return new RedirectResponse(
                        $this->generateUrl(
                            'DemosPlan_procedure_member_index',
                            ['procedure' => $procedure]
                        )
                    );
                } catch (NoRecipientsWithEmailException) {
                    // generiere eine Fehlermeldung, wenn nur Empfänger ohne Email ausgesucht wurden.
                    $this->getMessageBag()->add(
                        'error',
                        'error.email.invitation.no.recipients.with.mail',
                        ['variable' => '']
                    );

                    return $this->redirectBack($request);
                } catch (MissingDataException) {
                    $this->getMessageBag()->add(
                        'error',
                        'error.missing.subject.or.text'
                    );

                    return $this->redirectBack($request);
                } catch (Exception) {
                    $this->getMessageBag()->add(
                        'error',
                        'error.email.invitation.send',
                        ['variable' => '']
                    );

                    return $this->redirectBack($request);
                }
            }
        }

        $templateVars['emailTextAdded'] = $emailTextAdded;
        // aus Legacygründen filtere die Zeichenkette Text aus
        if ('Text' === $templateVars['procedure']['settings']['emailText']) {
            $templateVars['procedure']['settings']['emailText'] = '';
        }

        $templateVars['search'] = $search;

        // Füge die kontextuelle Hilfe dazu
        $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp($title);

        // hole die Textbausteine
        $procedureService = $this->procedureService;
        $templateVars['boilerplates'] = $procedureService->getBoilerplatesOfCategory($procedure, 'email');

        // an welche Töb wurde eine Email geschickt?
        $templateVars['orgaInvitationemailSent'] = [];
        $invitationEmailSent = $serviceOutput->getInvitationEmailSentList(
            $procedure,
            $templateVars['procedure']['phase']
        );
        if (\is_array($invitationEmailSent['result']) && 0 < count($invitationEmailSent['result'])) {
            foreach ($invitationEmailSent['result'] as $invitedOrga) {
                if (\array_key_exists('organisation', $invitedOrga)
                    && $invitedOrga['organisation'] instanceof Orga
                ) {
                    $templateVars['orgaInvitationemailSent'][] = $invitedOrga['organisation']->getId();
                }
            }
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_member_list.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $title,
                'procedure'    => $procedure,
            ]
        );
    }

    /**
     * TöB hinzufügen Liste.
     *
     * @DplanPermissions({"area_main_procedures","area_admin_invitable_institution"})
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_member_add', path: '/verfahren/{procedure}/einstellungen/benutzer/hinzufuegen')]
    public function administrationNewMemberListAction(
        Breadcrumb $breadcrumb,
        MessageBagInterface $messageBag,
        OrgaService $orgaService,
        Request $request,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator,
        string $procedure,
    ) {
        $templateVars = [];
        $requestPost = $request->request->all();

        if (\array_key_exists('orga_add', $requestPost)) {
            $addorgas = $requestPost['orga_add'];
            $storageResult = $serviceStorage->addOrgaToProcedureHandler($procedure, $addorgas);

            if (true === $storageResult) {
                $messageBag->add(
                    'confirm',
                    $translator->trans('confirm.invitable_institutions.added')
                );
            } else {
                $messageBag->add(
                    'warning',
                    $translator->trans('warning.invitable_institution.not.added')
                );
            }

            return new RedirectResponse(
                $this->generateUrl(
                    'DemosPlan_procedure_member_index',
                    [
                        'procedure' => $procedure,
                    ]
                )
            );
        }

        $templateVars['orgas'] = $orgaService->getInvitablePublicAgencies();

        // reichere die breadcrumb mit extraItem an (TöB verwalten)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans(
                    'procedure.public.agency.administration',
                    [],
                    'page-title'
                ),
                'url'   => $this->generateUrl('DemosPlan_procedure_member_index', ['procedure' => $procedure]),
            ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_new_member_list.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.public.agency.add',
                'procedure'    => $procedure,
            ]
        );
    }

    /**
     * Hole die Liste der Textbausteine, gegebenfalls lösche markeirte Textbausteine.
     *
     * @DplanPermissions("area_admin_boilerplates")
     *
     * @param string $procedure
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_boilerplate_list', path: '/verfahren/{procedure}/textbausteine', options: ['expose' => true])]
    public function boilerplateListAction(
        ProcedureHandler $procedureHandler,
        Request $request,
        $procedure,
    ) {
        $procedureId = $procedure;
        $requestPost = $request->request;
        $procedureService = $this->procedureService;

        // Delete checked Boilerplates and or BoilerPlateGroups
        if ($requestPost->has('boilerplateDeleteChecked')) {
            // Delete checked Boilerplates
            if ($requestPost->has('boilerplate_delete')) {
                $this->handleDeleteBoilerplates($procedureHandler, $requestPost->get('boilerplate_delete'));
            }

            // Delete checked BoilerplateGroups
            if ($requestPost->has('boilerplateGroupIdsTo_delete')) {
                $this->handleDeleteBoilerplateGroups($requestPost->get('boilerplateGroupIdsTo_delete'));
            }

            if (false ===
                $requestPost->has('boilerplate_delete') || $requestPost->has('boilerplateGroupIdsTo_delete')
            ) {
                $this->getMessageBag()->add('warning', 'warning.select.entries');
            }
        }

        // delete single boilerplate
        if ($requestPost->has('boilerplateDeleteItem')) {
            $this->handleDeleteBoilerplate($requestPost->get('boilerplateDeleteItem'));
        }

        // delete single boilerplateGroup
        if ($requestPost->has('boilerplateGroupDeleteAllContent')) {
            $this->handleDeleteBoilerplateGroup($requestPost->get('boilerplateGroupDeleteAllContent'));
        }

        if ($requestPost->has('r_createGroup') && $requestPost->has('r_newGroup')) {
            try {
                $createdGroup = $procedureService->createBoilerplateGroup($requestPost->get('r_newGroup'), $procedureId);
                $this->getMessageBag()->add(
                    'confirm',
                    'confirm.boilerplate.group.created',
                    ['title' => $createdGroup->getTitle()]
                );
            } catch (Exception) {
                $this->getMessageBag()->add(
                    'error',
                    'error.boilerplate.group.not.created',
                    ['title' => $requestPost['r_newGroup']]
                );
            }
        }

        $templateVars = [];
        $templateVars['list'] = $procedureService->getBoilerplateList($procedure);
        $templateVars['boilerplateGroups'] = $procedureService->getBoilerplateGroups($procedureId);

        if (!$request->isMethod('GET')) {
            // prevent sending the same post multiple times when browser reloads the same page (F5)
            // therefore redirect to self as a get call instead.
            return $this->redirectBack($request);
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_list_boilerplate.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.boilerplates',
                'procedure'    => $procedure,
            ]
        );
    }

    /**
     * Creation and editing of places, each is either process or procedure template related.
     *
     * @DplanPermissions("area_manage_segment_places")
     */
    #[Route(name: 'DemosPlan_procedure_places_list', path: '/verfahren/{procedureId}/schritte', options: ['expose' => true])]
    #[Route(name: 'DemosPlan_procedure_template_places_list', path: '/verfahren/blaupause/{procedureId}/schritte')]
    public function showProcedurePlacesAction(string $procedureId)
    {
        return $this->renderTemplate('@DemosPlanCore/DemosPlanProcedure/administration_places.html.twig', [
            'procedureId' => $procedureId,
        ]);
    }

    /**
     * Creation of custom fields, each is either procedure or procedure template related.
     */
    #[AttributeDplanPermissions('area_admin_custom_fields')]
    #[Route(name: 'DemosPlan_procedure_custom_fields_list', path: '/verfahren/{procedureId}/konfigurierbareFelder', options: ['expose' => true])]
    #[Route(name: 'DemosPlan_procedure_template_custom_fields_list', path: '/verfahren/blaupause/{procedureId}/konfigurierbareFelder', options: ['expose' => true])]
    public function showProcedureCustomFieldsAction(string $procedureId)
    {
        $templateVars['procedureTemplate'] = $this->currentProcedureService->getProcedure()?->getMaster() ?? false;
        $templateVars['procedureId'] = $procedureId;

        return $this->renderTemplate('@DemosPlanCore/DemosPlanProcedure/administration_custom_fields_list.html.twig',
            ['templateVars' => $templateVars]);
    }

    /**
     * Bearbeite bestehende und neue Textbausteine.
     *
     * @DplanPermissions("area_admin_boilerplates")
     *
     * @param string $procedure
     * @param string $boilerplateId
     * @param string $selectedGroupId
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_procedure_boilerplate_edit', path: '/verfahren/{procedure}/textbaustein/{boilerplateId}/{selectedGroupId}', defaults: ['boilerplateId' => 'new', 'selectedGroupId' => ''])]
    public function boilerplateEditAction(FormFactoryInterface $formFactory, Request $request, $procedure, $boilerplateId, $selectedGroupId)
    {
        $boilerplateValueObject = new BoilerplateVO();
        $updatedBoilerplate = null;
        $procedureService = $this->procedureService;

        if ('new' !== $boilerplateId) {
            $boilerplate = $procedureService->getBoilerplateById($boilerplateId);
            if (null !== $boilerplate) {
                $boilerplateValueObject = new BoilerplateVO($boilerplate);
            } else {
                $this->logger->warning('no Boilerplate found for ID '.$boilerplateId);
            }
        } elseif ('' !== $selectedGroupId) {
            $selectedGroup = $procedureService->getBoilerplateGroup($selectedGroupId);
            if (null !== $selectedGroup) {
                $boilerplateValueObject->setGroup($selectedGroup);
            }
        }
        $form = $formFactory->createNamed(
            // we don't use form names for data evaluation, see
            // https://symfony.com/doc/5.4/forms.html#changing-the-form-name
            '',
            BoilerplateType::class,
            $boilerplateValueObject,
            [
                'csrf_protection'    => true,
                'allow_extra_fields' => true, // action field (input, not the one from form)
            ]
        );
        $form->handleRequest($request);

        $confirmMessage = 'confirm.boilerplate.edited';
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var BoilerplateVO $boilerplate */
            $boilerplate = $form->getData();
            // boilerplateId is set to 'new' via route defaults if the value in form is empty
            // string comparison for readable urls
            if ('new' === $boilerplateId) {
                $updatedBoilerplate = $procedureService->addBoilerplateVO($procedure, $boilerplate);
                $confirmMessage = 'confirm.boilerplate.created';
            } else {
                $updatedBoilerplate = $procedureService->updateBoilerplateVO($boilerplate);
            }
        }

        $errors = $form->getErrors(true, true);
        $this->writeErrorsIntoMessageBag($errors);
        $error = 0 < $errors->count();

        // because this edit route is also used to get to the detail view of the boilerplate, we have
        // to determine if we should load the detail view or the list view
        // in case of successfully update, redirect to list
        if (!$error && $updatedBoilerplate instanceof Boilerplate) {
            $this->getMessageBag()->add('confirm', $confirmMessage);

            return $this->redirectToRoute('DemosPlan_procedure_boilerplate_list', [
                'procedure' => $procedure,
                '_fragment' => $updatedBoilerplate->getId(),
            ]);
        }

        $includeNewsCategory = $this->permissions->hasPermission('area_news');
        $includeEmailCategory = $this->permissions->hasPermissions(
            [
                'field_send_final_email', // "Classic Schlussmitteilung"
                'area_admin_invitable_institution', // There seems to be no distinct permission to invite orgas to participate...
                'area_procedure_send_submitter_email', // "E-Mail an alle Einreichenden"
                'area_invite_unregistered_public_agencies', // "Unregistrierte TöB einladen"
                'area_customer_send_mail_to_users', // E-mail to all customer users
            ],
            'OR'
        );

        try {
            $boilerplateCategories = $procedureService->getBoilerplateCategoryList(
                $procedure,
                $includeNewsCategory,
                $includeEmailCategory
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to load boilerplate categories', [$procedure, $e]);
            $boilerplateCategories = [];
        }
        $boilerplateGroups = $procedureService->getBoilerplateGroups($procedure);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_edit_boilerplate.html.twig',
            [
                'form'                         => $form->createView(),
                'boilerplateCategories'        => $boilerplateCategories,
                'boilerplateGroupsOfProcedure' => $boilerplateGroups,
                'selectedGroup'                => '',
                'title'                        => 'procedure.boilerplate.edit',
                'procedure'                    => $procedure,
            ]
        );
    }

    /**
     * @DplanPermissions("area_admin_boilerplates")
     *
     * @param string $procedure
     * @param string $boilerplateGroupId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_boilerplate_group_delete', path: '/verfahren/{procedure}/boilerplate/{boilerplateGroupId}/delete')]
    public function boilerplateGroupDeleteAction(Request $request, $procedure, $boilerplateGroupId)
    {
        $procedureService = $this->procedureService;

        $boilerplateGroup = $procedureService->getBoilerplateGroup($boilerplateGroupId);
        $boilerplateTitle = null === $boilerplateGroup ? '' : $boilerplateGroup->getTitle();
        $successfully = $procedureService->deleteBoilerplateGroup($boilerplateGroup);

        if ($successfully) {
            $this->getMessageBag()->add('confirm', 'confirm.boilerplateGroup.deleted', ['title' => $boilerplateTitle]);
            $this->getLogger()->info('boilerplateGroup deleted '.$boilerplateGroupId);
        } else {
            $this->getMessageBag()->add('warning', 'warning.boilerplateGroup.deleted');
            $this->getLogger()->warning('no Boilerplate found for ID '.$boilerplateGroupId);
        }

        return $this->redirectToRoute('DemosPlan_procedure_boilerplate_list', [
            'procedure' => $procedure,
        ]);
    }

    /**
     * @DplanPermissions("area_admin_boilerplates")
     *
     * @param string $procedure
     * @param string $boilerplateGroupId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_boilerplate_group_edit', path: '/verfahren/{procedure}/boilerplategroup/{boilerplateGroupId}', defaults: ['boilerplateGroupId' => 'new'], options: ['expose' => true])]
    public function boilerplateGroupEditAction(FormFactoryInterface $formFactory, Request $request, $procedure, $boilerplateGroupId)
    {
        $boilerplateGroupValueObject = new BoilerplateGroupVO();
        $updatedBoilerplateGroup = null;
        $procedureService = $this->procedureService;

        if ('new' !== $boilerplateGroupId) {
            $boilerplateGroup = $procedureService->getBoilerplateGroup($boilerplateGroupId);
            if (null !== $boilerplateGroup) {
                $boilerplateGroupValueObject = new BoilerplateGroupVO($boilerplateGroup);
            } else {
                $this->logger->warning('no Boilerplate found for ID '.$boilerplateGroupId);
            }
        }

        $form = $formFactory->createNamed(
            // we don't use form names for data evaluation, see
            // https://symfony.com/doc/5.4/forms.html#changing-the-form-name
            '',
            BoilerplateGroupType::class,
            $boilerplateGroupValueObject,
            [
                'csrf_protection'    => false,
                'allow_extra_fields' => true, // action field (input, not the one from form)
            ]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var BoilerplateGroupVO $boilerplateGroupVO */
            $boilerplateGroupVO = $form->getData();
            if ('new' === $boilerplateGroupId) {
                $updatedBoilerplateGroup = $procedureService->addBoilerplateGroupVO($procedure, $boilerplateGroupVO);
            } else {
                $updatedBoilerplateGroup = $procedureService->updateBoilerplateGroupVO($boilerplateGroupVO);
            }
        }

        $errors = $form->getErrors(true, true);
        $this->writeErrorsIntoMessageBag($errors);
        $error = 0 < $errors->count();

        if (!$error && $updatedBoilerplateGroup instanceof BoilerplateGroup) {
            if ('new' === $boilerplateGroupId) {
                $this->getMessageBag()->add('confirm', 'confirm.boilerplateGroup.created', ['title' => $updatedBoilerplateGroup->getTitle()]);
            } else {
                $this->getMessageBag()->add('confirm', 'confirm.boilerplateGroup.updated', ['title' => $updatedBoilerplateGroup->getTitle()]);
            }

            return $this->redirectToRoute('DemosPlan_procedure_boilerplate_list', [
                'procedure' => $procedure,
                '_fragment' => $updatedBoilerplateGroup->getId(),
            ]);
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_edit_boilerplate_group.html.twig',
            [
                'form'      => $form->createView(),
                'title'     => 'procedure.boilerplateGroup.edit',
                'procedure' => $procedure,
            ]
        );
    }

    /**
     * Is User allowed to enter procedure e.g by using a deeplink.
     */
    protected function canAccessProcedure(): void
    {
        if ($this->permissions->ownsProcedure()) {
            return;
        }

        if ($this->permissions->isMember() && $this->permissions->hasPermissionsetRead()) {
            return;
        }

        throw new AccessDeniedException('Access denied to the procedure.');
    }

    /**
     * Deletes the given boilerplate and create message for user.
     *
     * @param string $boilerplateId - Identifies the Boilerplate to delete
     *
     * @throws MessageBagException
     */
    protected function handleDeleteBoilerplate(string $boilerplateId)
    {
        $boilerplateToDelete = $this->procedureService->getBoilerplate($boilerplateId);
        $title = null === $boilerplateToDelete ? '' : $boilerplateToDelete->getTitle();
        $successfully = $this->procedureService->deleteBoilerplate($boilerplateId);
        if ($successfully) {
            $this->getMessageBag()->add(
                'confirm',
                'confirm.boilerplate.deleted',
                ['title' => $title]
            );
        } else {
            $this->getMessageBag()->add('warning', 'warning.boilerplate.delete');
        }
    }

    /**
     * Deletes the given boilerplateGroup and create message for user.
     *
     * @throws MessageBagException
     */
    protected function handleDeleteBoilerplateGroup(
        string $boilerplateGroupId,
    ) {
        $boilerplatesOfGroupToDelete = new ArrayCollection();
        $boilerplateGroupToDelete = $this->procedureService->getBoilerplateGroup($boilerplateGroupId);
        $title = '';
        if (null !== $boilerplateGroupToDelete) {
            foreach ($boilerplateGroupToDelete->getBoilerplates() as $boilerplate) {
                $boilerplatesOfGroupToDelete->add($boilerplate);
            }
            $title = $boilerplateGroupToDelete->getTitle();
        }

        $successfully = $this->procedureService->deleteBoilerplateGroup($boilerplateGroupToDelete);
        /** @var Boilerplate $boilerplate */
        foreach ($boilerplatesOfGroupToDelete as $boilerplate) {
            $successfully = $successfully && $this->procedureService->deleteBoilerplate($boilerplate->getId());
        }
        if ($successfully) {
            $this->getMessageBag()->add(
                'confirm',
                'confirm.boilerplateGroup.deleted',
                ['title' => $title]
            );
        } else {
            $this->getMessageBag()->add('warning', 'warning.boilerplateGroup.delete');
        }
    }

    /**
     * Deletes the given boilerplates and create message for user.
     *
     * @throws MessageBagException
     */
    protected function handleDeleteBoilerplates(
        ProcedureHandler $procedureHandler,
        array $boilerplateIds,
    ) {
        $storageResult = $procedureHandler->deleteBoilerplates($boilerplateIds);
        if (true === $storageResult) {
            $this->getMessageBag()->add('confirm', 'confirm.selected.boilerplates.deleted');
        } else {
            $this->getMessageBag()->add('warning', 'warning.selected.boilerplates.delete');
        }
    }

    /**
     * Deletes the given boilerplateGroups and create message for user.
     *
     * @throws MessageBagException
     */
    protected function handleDeleteBoilerplateGroups(
        array $boilerplateGroupIds,
    ) {
        $boilerplatesOfGroupsToDelete = new ArrayCollection();
        foreach ($boilerplateGroupIds as $boilerplateGroupId) {
            $boilerplateGroup = $this->procedureService->getBoilerplateGroup($boilerplateGroupId);
            /* @var Boilerplate $boilerplate */
            if (null === $boilerplateGroup) {
                continue;
            }
            foreach ($boilerplateGroup->getBoilerplates() as $boilerplate) {
                $boilerplatesOfGroupsToDelete->add($boilerplate);
            }
        }
        $allDeleted = $this->procedureService->deleteBoilerplateGroupsByIds($boilerplateGroupIds);
        /** @var Boilerplate $boilerplate */
        foreach ($boilerplatesOfGroupsToDelete as $boilerplate) {
            $allDeleted = $allDeleted && $this->procedureService->deleteBoilerplate($boilerplate->getId());
        }
        if ($allDeleted) {
            $this->getMessageBag()->add('confirm', 'confirm.selected.boilerplateGroups.deleted');
        } else {
            $this->getMessageBag()->add('warning', 'warning.selected.boilerplateGroups.delete');
        }
    }

    /**
     * Gets and adds the ProcedureTypes to the templateVars, if it makes sense to do so.
     *
     * @return array Returns the templateVars with the added array of ProcedureTypes
     */
    protected function addProcedureTypesToTemplateVars(
        array $templateVars,
        bool $isProcedureTemplate,
    ): array {
        // procedure types are completely irrelevant in procedure templates (Blaupausen), so no need
        // to pass the variable if it's a procedure template (Blaupause)
        if ($isProcedureTemplate) {
            return $templateVars;
        }

        $nameSorting = $this->sortMethodFactory->propertyAscending($this->procedureTypeResourceType->name);
        $templateVars['procedureTypes'] = $this->procedureTypeResourceType->getEntities([], [$nameSorting]);

        return $templateVars;
    }

    /**
     * @throws MessageBagException
     * @throws InvalidArgumentException
     */
    private function validateAdministrationEditInput(array $input): void
    {
        $error = false;
        if (
            \array_key_exists('r_publicParticipationContact', $input)
            && \strlen((string) $input['r_publicParticipationContact']) > Procedure::MAX_PUBLIC_PARTICIPATION_CONTACT_LENGTH
        ) {
            $this->getMessageBag()->add('error', 'adjustments.general.error.public.participation.maxlength');
            $error = true;
        }
        if (
            \array_key_exists('r_externalDesc', $input)
            && \strlen((string) $input['r_externalDesc']) > Procedure::MAX_PUBLIC_DESCRIPTION_LENGTH
        ) {
            $this->getMessageBag()->add('error', 'adjustments.general.error.public.procedure.description.maxlength');
            $error = true;
        }

        if ($error) {
            throw new InvalidArgumentException();
        }
    }

    private function eMailTitleContainsNewProcedureName(string $eMailTitle, string $newProcedureName): bool
    {
        return str_contains(strtolower($eMailTitle), strtolower($newProcedureName));
    }

    private function eMailTitleContainsOldProcedureName(string $eMailTitle, string $oldProcedureName): bool
    {
        return str_contains(strtolower($eMailTitle), strtolower($oldProcedureName));
    }

    protected function getStatements(StatementService $statementService, string $procedureId): ElasticsearchResultSet
    {
        $statusPriorityAggregation = new MultiTermsAggregation(self::AGGREGATION_STATUS_PRIORITY);
        $statusPriorityAggregation->addTerm(StatementService::FIELD_STATEMENT_STATUS);
        $statusPriorityAggregation->addTerm(StatementService::FIELD_STATEMENT_PRIORITY);

        return $statementService->getStatementsByProcedureId(
            $procedureId,
            ['isPlaceholder' => false],
            null,
            null,
            0,
            1,
            [],
            true,
            0,
            true,
            true,
            [$statusPriorityAggregation]
        );
    }
}
