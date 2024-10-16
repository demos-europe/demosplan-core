<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\AssessmentTable;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\ClusterStatementCopyNotImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNameTooLongException;
use demosplan\DemosPlanCoreBundle\Form\StatementBulkEditType;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\News\ServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentExportOptions;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CountyService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\MunicipalityService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\PriorityAreaService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFilterHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\Services\HTMLFragmentSlicer;
use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\StatementBulkEditVO;
use Doctrine\Common\Collections\Collection;
use Exception;
use FOS\ElasticaBundle\Index\IndexManager;
use LogicException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function compact;
use function nl2br;
use function strlen;

/**
 * Klasse fuer die Abwaegungstabelle.
 */
class DemosPlanAssessmentTableController extends BaseController
{
    use RefreshElasticsearchIndexTrait;

    private const HASH_TYPE_ASSESSMENT = 'assessment';
    private const HASH_TYPE_ORIGINAL = 'original';

    public function __construct(private readonly Breadcrumb $breadcrumb, private readonly CountyService $countyService, private readonly IndexManager $indexManager, private readonly MunicipalityService $municipalityService, private readonly PermissionsInterface $permissions, private readonly PriorityAreaService $priorityAreaService, private readonly ProcedureService $procedureService, private readonly StatementHandler $statementHandler, private readonly UserService $userService)
    {
    }

    /**
     * Abwaegungstabelle - Liste.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/assessment_table/ Wiki: Abwägungstabelle
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param AssessmentExportOptions $exportOptions Object that holds logic about export options in normal and original view
     * @param string|null             $filterHash
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_assessmenttable_view_table', path: '/verfahren/abwaegung/view/{procedureId}/{filterHash}', defaults: ['filterHash' => null, 'original' => false], options: ['expose' => true])]
    public function viewTableAction(
        AssessmentExportOptions $exportOptions,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentUserInterface $currentUser,
        Request $request,
        AssessmentHandler $assessmentHandler,
        HashedQueryService $filterSetService,
        ProcedureService $procedureService,
        StatementFilterHandler $statementFilterHandler,
        StatementHandler $statementHandler,
        StatementService $statementService,
        string $procedureId,
        $filterHash,
        bool $original
    ): ?Response {
        // @improve T14122

        // Generic method to get rParams and also set default values
        $rParams = $assessmentHandler->getFormValues($request->request->all());

        // handle the filterHash thing → always returns FilterSet Entity, except → see next comment
        $findHash = $filterSetService->findHashedQueryWithHash($filterHash);
        if ($filterHash) {
            $storedQuery = $findHash->getStoredQuery();
            if (
                $rParams['search'] === $storedQuery->getSearchWord()
            ) {
                $filterSet = $findHash;
            } else {
                /*
                * If rParams contain filters, those win against the hash in url.
                * Doing this via redirect to same action.
                */
                $filterSet = $assessmentHandler->handleFilterHash($request, $procedureId, null, $original);
                $this->setHashforEmptyFilters($request, $assessmentHandler, $procedureId, $filterSet);
            }
        } else {
            /*
            * If rParams contain filters, those win against the hash in url.
            * Doing this via redirect to same action.
            */
            $filterSet = $assessmentHandler->handleFilterHash($request, $procedureId, null, $original);
            $this->setHashforEmptyFilters($request, $assessmentHandler, $procedureId, $filterSet);
        }

        $type = self::HASH_TYPE_ASSESSMENT;

        // Get the AssessmentQueryValueObject → holds all we need
        /** @var AssessmentTableQuery $assessmentTableQuery */
        $assessmentTableQuery = $filterSet->getStoredQuery();

        // !!! But don't call handleFilterHash here!!! was pretty hard work to get it out of this place !!!
        $this->prepareHashListWithDefaults($request, $procedureId, $type, $rParams, $filterHash);

        // Paginator
        $hashList = $request->getSession()->get('hashList');

        /**
         * If the limit changes, we want to reset the page to 1.
         * If there is no limit, we want to take the new or old page
         * with the same limit for paginator.
         */
        $paginationData = $hashList[$procedureId][$type] ?? null;
        $rParams = array_key_exists('limit', $rParams['request'])
            ? $statementService->addPaginationToParams(1, null, $rParams)
            : $statementService->addPaginationToParams($paginationData['page'], $paginationData['r_limit'], $rParams);

        if ($this->permissions->hasPermission('feature_procedure_user_filter_sets')
            && $request->request->has('r_save_filter_set_name')) {
            $assessmentHandler->saveUserFilterSet(
                $currentUser->getUser(),
                $procedureId,
                $request,
                $filterSet
            );

            // avoid duplicate save of filter in case of reload site
            return $this->redirectToRoute(
                'dplan_assessmenttable_view_table',
                [
                    'procedureId' => $procedureId,
                    'filterHash'  => $filterSet->getHash(),
                ]
            );
        }

        $doRedirect = null === $filterHash;
        $filterHash = $filterSet->getHash();
        $redirectParameters = compact('procedureId', 'filterHash');

        /*
         * Not sure if this is right. Think it's there to handle original table view.
         * What about giving a hash to original? Hope it's handled.
         * Think this could be coded more compact by adding logic to the section above.
         * It does similar things → should be in the same spot
         */
        if ($doRedirect) {
            return $this->redirectToRoute('dplan_assessmenttable_view_table', $redirectParameters);
        }

        // Put viewMode and filterHash in templateVars
        /** @var AssessmentTableViewMode|null $viewMode */
        $viewMode = $original ? null : $assessmentTableQuery->getViewMode();
        $rParams = $statementService->integrateFilterSetIntoArray(
            $filterSet,
            $rParams,
            $original
        );

        // Handling viewMode
        try {
            // Default request
            $table = $assessmentTableServiceOutput->getStatementListHandler(
                $procedureId,
                $rParams,
                true,
                1,
                false
            );
        } catch (Exception $e) {
            $this->logger->error('Could not get statements for assessmenttable with view_mode '.$viewMode.': ', [$e]);
            throw $e;
        }

        // @improve T12376
        /*
         * refs T5109, T5205: avoid resend delete or copy action on reload of the website
         * The thing is, that the actions are handled before. So this is just a redirect after
         * the work is done. this happens at the same time we do the es request:
         * {@link AssessmentTableServiceOutput::getStatementListHandler}
         * beside getting statements there is a line:
         * {@link AssessmentTableServiceStorage::startServiceAction}
         * this is where the magic happens. Could take very long to find this...
         */
        if (array_key_exists('request', $rParams) && array_key_exists('action', $rParams['request'])) {
            switch ($rParams['request']['action']) {
                case 'copy':
                case 'delete':
                    // when copying from original list stay in this list to avoid
                    // passing wrong filterhash to assessmenttable and vice versa
                    return $this->redirectToRoute('dplan_assessmenttable_view_table', $redirectParameters);
            }
        }

        $table = $assessmentTableServiceOutput->mapTable(
            $table,
            $viewMode,
            $procedureId,
            $rParams['search'] ?? null
        );

        $accessibleProcedureIds = $this->permissions->hasPermission('feature_statement_move_to_procedure')
            ? $procedureService->getAccessibleProcedureIds($currentUser->getUser(), $procedureId)
            : [];

        $templateVars = [
            'viewMode'                        => $viewMode,
            'filterHash'                      => $filterHash,
            'totalResults'                    => $table->getTotal(),
            'limitResults'                    => $rParams['request']['limit'] ?? null,
            'pager'                           => $table->getPager(),
            'table'                           => $table->toArray(),
            'filterName'                      => $statementFilterHandler->getFilterLabelMap(),
            'sortingDirections'               => $this->getSortingDirections(),
            'statementFragmentAgencies'       => $statementHandler->getAgencyData(),
            'assessmentExportOptions'         => $exportOptions->get('assessment_table'),
            'authorizedUsersOfMyOrganization' => $procedureService->getAuthorizedUsers(
                $procedureId
            ),
            'accessibleProcedureIds'          => $accessibleProcedureIds,
            'defaultToggleView'               => $this->globalConfig->getAssessmentTableDefaultToggleView(),
        ];

        $usedFilters = $statementFilterHandler->getRequestedFiltersInfo(
            $assessmentTableQuery->getFilters(),
            $table->getFilterSet()['filters']
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanAssessmentTable/DemosPlan/dhtml/v1/assessment_table_view.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'assessment.table',
                'procedure'    => $procedureId,
                'filters'      => $usedFilters,
            ]
        );
    }

    private function setHashforEmptyFilters(Request $request, AssessmentHandler $assessmentHandler, string $procedureId, HashedQuery $filterSet)
    {
        $request = $this->updateFilterSetParametersInRequest($request, $assessmentHandler);

        return $this->redirectToRoute(
            'dplan_assessmenttable_view_table',
            [
                'procedureId' => $procedureId,
                'filterHash'  => $filterSet->getHash(),
                '_fragment'   => $request->query->get('fragment', ''),
            ]
        );
    }

    /**
     * Original Statements list.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/assessment_table/ Wiki: Abwägungstabelle
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param AssessmentExportOptions $exportOptions Object that holds logic about export options in normal and original view
     * @param string|null             $filterHash
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_assessmenttable_view_original_table', path: '/verfahren/original/{procedureId}/{filterHash}', defaults: ['filterHash' => null, 'original' => true], options: ['expose' => true])]
    public function viewOriginalTableAction(
        AssessmentExportOptions $exportOptions,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CountyService $countyService,
        CurrentUserInterface $currentUser,
        Request $request,
        AssessmentHandler $assessmentHandler,
        HashedQueryService $filterSetService,
        MunicipalityService $municipalityService,
        PriorityAreaService $priorityAreaService,
        ProcedureService $procedureService,
        StatementFilterHandler $statementFilterHandler,
        StatementHandler $statementHandler,
        StatementService $statementService,
        TranslatorInterface $translator,
        string $procedureId,
        $filterHash,
        bool $original
    ): ?Response {
        // @improve T14122

        // Generic method to get rParams and also set default values
        $rParams = $assessmentHandler->getFormValues($request->request->all());

        // handle the filterHash thing → always returns FilterSet Entity, except → see next comment
        $filterSet = $filterSetService->findHashedQueryWithHash($filterHash);

        $type = self::HASH_TYPE_ORIGINAL;

        /*
         * If rParams contain filters, those win against the hash in url.
         * Doing this via redirect to same action.
         */
        if (null === $filterSet) {
            $request = $this->updateFilterSetParametersInRequest($request, $assessmentHandler);
            $filterSet = $assessmentHandler->handleFilterHash($request, $procedureId, null, $original);

            return $this->redirectToRoute(
                'dplan_assessmenttable_view_original_table',
                [
                    'procedureId' => $procedureId,
                    'filterHash'  => $filterSet->getHash(),
                    '_fragment'   => $request->query->get('fragment', ''),
                ]
            );
        }

        // Get the AssessmentQueryValueObject → holds all we need
        /** @var AssessmentTableQuery $assessmentTableQuery */
        $assessmentTableQuery = $filterSet->getStoredQuery();

        // !!! But don't call handleFilterHash here!!! was pretty hard work to get it out of this place !!!
        $this->prepareHashListWithDefaults($request, $procedureId, $type, $rParams, $filterHash);

        // Paginator
        $hashList = $request->getSession()->get('hashList');

        /**
         * If the limit changes, we want to reset the page to 1.
         * If there is no limit, we want to take the new or old page
         * with the same limit for paginator.
         */
        $paginationData = $hashList[$procedureId][$type] ?? null;
        $rParams = array_key_exists('limit', $rParams['request'])
            ? $statementService->addPaginationToParams(1, null, $rParams)
            : $statementService->addPaginationToParams($paginationData['page'], $paginationData['r_limit'], $rParams);

        if ($this->permissions->hasPermission('feature_procedure_user_filter_sets')
            && $request->request->has('r_save_filter_set_name')) {
            $assessmentHandler->saveUserFilterSet(
                $currentUser->getUser(),
                $procedureId,
                $request,
                $filterSet
            );

            // avoid duplicate save of filter in case of reload site
            return $this->redirectToRoute(
                'dplan_assessmenttable_view_original_table',
                [
                    'procedureId' => $procedureId,
                    'filterHash'  => $filterSet->getHash(),
                ]
            );
        }

        $doRedirect = null === $filterHash;
        $filterHash = $filterSet->getHash();
        $redirectParameters = compact('procedureId', 'filterHash');

        /*
         * Not sure if this is right. Think it's there to handle original table view.
         * What about giving a hash to original? Hope it's handled.
         * Think this could be coded more compact by adding logic to the section above.
         * It does similar things → should be in the same spot
         */
        if ($doRedirect) {
            return $this->redirectToRoute('dplan_assessmenttable_view_original_table', $redirectParameters);
        }

        // Put viewMode and filterHash in templateVars
        /** @var AssessmentTableViewMode|null $viewMode */
        $viewMode = $original ? null : $assessmentTableQuery->getViewMode();

        $rParams = $statementService->integrateFilterSetIntoArray(
            $filterSet,
            $rParams,
            $original
        );

        // Handling viewMode
        try {
            // Default request
            $table = $assessmentTableServiceOutput->getStatementListHandler(
                $procedureId,
                $rParams,
                false,
                1,
                false
            );
        } catch (Exception $e) {
            $this->logger->error('Could not get statements for assessmenttable with view_mode '.$viewMode.': ', [$e]);
            throw $e;
        }

        // @improve T12376
        /*
         * refs T5109, T5205: avoid resend delete or copy action on reload of the website
         * The thing is, that the actions are handled before. So this is just a redirect after
         * the work is done. this happens at the same time we do the es request:
         * {@link AssessmentTableServiceOutput::getStatementListHandler}
         * beside getting statements there is a line:
         * {@link AssessmentTableServiceStorage::startServiceAction}
         * this is where the magic happens. Could take very long to find this...
         */
        if (array_key_exists('request', $rParams) && array_key_exists('action', $rParams['request'])) {
            switch ($rParams['request']['action']) {
                case 'copy':
                case 'delete':
                    // when copying from original list stay in this list to avoid
                    // passing wrong filterhash to assessmenttable and vice versa
                    return $this->redirectToRoute('dplan_assessmenttable_view_original_table', $redirectParameters);
            }
        }

        $table = $assessmentTableServiceOutput->mapTable(
            $table,
            $viewMode,
            $procedureId,
            $rParams['search'] ?? null
        );

        $accessibleProcedureIds = $this->permissions->hasPermission('feature_statement_move_to_procedure')
            ? $procedureService->getAccessibleProcedureIds($currentUser->getUser(), $procedureId)
            : [];

        $templateVars = [
            'viewMode'                        => $viewMode,
            'filterHash'                      => $filterHash,
            'totalResults'                    => $table->getTotal(),
            'limitResults'                    => $rParams['request']['limit'] ?? null,
            'pager'                           => $table->getPager(),
            'table'                           => $table->toArray(),
            'filterName'                      => $statementFilterHandler->getFilterLabelMap(),
            'sortingDirections'               => $this->getSortingDirections(),
            'statementFragmentAgencies'       => $statementHandler->getAgencyData(),
            'assessmentExportOptions'         => $exportOptions->get('original_statements'),
            'authorizedUsersOfMyOrganization' => $procedureService->getAuthorizedUsers(
                $procedureId
            ),
            'accessibleProcedureIds'          => $accessibleProcedureIds,
            'defaultToggleView'               => $this->globalConfig->getAssessmentTableDefaultToggleView(),
        ];

        $baseData = [
            'adviceValues'      => $this->getFormParameter('statement_fragment_advice_values'),
            'tags'              => $statementHandler->getTopicsAndTagsOfProcedureAsArray($procedureId),
            'agencies'          => $statementHandler->getAgencyData(false),
            'defaultToggleView' => $this->globalConfig->getAssessmentTableDefaultToggleView(),
        ];

        // add base data for location information
        if ($this->permissions->hasPermission('field_statement_county')) {
            $baseData['counties'] = $countyService->getAllCountiesAsArray();
        }

        if ($this->permissions->hasPermission('field_statement_municipality')) {
            $baseData['municipalities'] = $municipalityService->getAllMunicipalitiesAsArray();
        }

        if ($this->permissions->hasPermission('field_statement_priority_area')) {
            $baseData['priorityAreas'] = $priorityAreaService->getAllPriorityAreasAsArray();
        }

        if ($this->permissions->hasPermission('field_statement_priority')) {
            $baseData['priorities'] = $this->getFormParameter('statement_priority');
        }

        if ($this->permissions->hasPermission('field_statement_status')) {
            $baseData['status'] = $this->getFormParameter('statement_status');
        }

        if ($this->permissions->hasPermission('field_fragment_status')) {
            $baseData['fragmentStatus'] = $this->getFormParameter('fragment_status');
        }

        // Verfahrensschritte
        $baseData['internalPhases'] = $this->globalConfig->getInternalPhases();
        $baseData['externalPhases'] = $this->globalConfig->getExternalPhases();

        $resElements = $statementHandler->getElementBlock($procedureId);
        $baseData['elements'] = $resElements['elements'] ?? [];
        $baseData['paragraph'] = $resElements['paragraph'] ?? [];
        $baseData['documents'] = $resElements['documents'] ?? [];

        // Get a procedure list to let user decide where to move a statement
        // Also check authentication
        if ($this->permissions->hasPermission('feature_statement_move_to_procedure')) {
            $baseData['accessibleProcedures'] = $procedureService->getAccessibleProcedureIds($currentUser->getUser(), $procedureId);

            if ($this->permissions->hasPermission('feature_statement_move_to_foreign_procedure')) {
                $baseData['inaccessibleProcedures'] = $procedureService->getInaccessibleProcedureIds($currentUser->getUser());
            }
        }

        // Get a procedure list to let user decide where to copy a statement
        // Also check authentication
        if ($this->permissions->hasPermission('feature_statement_copy_to_procedure')) {
            $baseData['accessibleProcedures'] = $procedureService->getAccessibleProcedureIds($currentUser->getUser(), $procedureId);

            if ($this->permissions->hasPermission('feature_statement_copy_to_foreign_procedure')) {
                $baseData['inaccessibleProcedures'] = $procedureService->getInaccessibleProcedureIds($currentUser->getUser());
            }
        }

        $templateVars['table']['baseData'] = Json::encode($baseData);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanAssessmentTable/DemosPlan/dhtml/v1/assessment_table_original_view.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'assessment.table',
                'procedure'    => $procedureId,
                'filters'      => $statementFilterHandler->getRequestedFiltersInfo(
                    $assessmentTableQuery->getFilters(),
                    $table->getFilterSet()['filters']
                ),
            ]
        );
    }

    /**
     * Set default values as filter parameters in the request.
     * See sister-method: createFilterSetParametersInArray.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/filterhash/ Wiki: Filterhashes
     */
    public function updateFilterSetParametersInRequest(Request $request, AssessmentHandler $assessmentHandler): Request
    {
        // set default vars
        $defaultFilterValues = $assessmentHandler->getProcedureDefaultFilter();

        // set default vars
        foreach ($defaultFilterValues as $key => $value) {
            $request->request->set($key, $value);
        }

        return $request;
    }

    // @improve T13718
    /**
     * Abwaegungstabelle - Detailseite.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param bool $isCluster Determines if the given Statement is a cluster
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_cluster_view', path: '/verfahren/{procedureId}/cluster/{statement}', defaults: ['title' => 'assessment.table.cluster.detail', 'isCluster' => true], options: ['expose' => true])]
    #[Route(name: 'dm_plan_assessment_single_view', path: '/verfahren/{procedureId}/abwaegung/sview/{statement}', defaults: ['title' => 'assessment.table.statement.detail'], options: ['expose' => true])]
    public function viewSingleAction(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentProcedureService $currentProcedureService,
        CurrentUserInterface $currentUser,
        EventDispatcherPostInterface $eventDispatcherPost,
        FileService $fileService,
        FileUploadService $fileUploadService,
        MapService $mapService,
        Request $request,
        ServiceOutput $serviceOutput,
        StatementService $statementService,
        TranslatorInterface $translator,
        $procedureId,
        $statement,
        $title,
        $isCluster = false
    ) {
        $fParams = [];
        $statementId = $statement;

        $rParams = $assessmentHandler->getFormValues($request->request->all());
        $fParams['files'] = $fileUploadService->prepareFilesUpload($request, 'r_fileupload');
        $fParams['r_email_attachments'] = $fileUploadService->prepareFilesUpload($request, 'r_attachments');
        $fParams[StatementAttachment::SOURCE_STATEMENT] = $fileUploadService->prepareFilesUpload($request, 'r_fileupload_'.StatementAttachment::SOURCE_STATEMENT);
        // die Schlussmitteilung wird in Kopie auch an den verschickenden User gesendet
        if ($this->permissions->hasPermission('feature_send_final_email_cc_to_self')) {
            $rParams['emailCC'] = $currentUser->getUser()->getEmail();
        }

        $statementObject = $this->statementHandler->getStatement($statementId);

        // get and update single statement
        $exceptionRedirectRouteParams = [
            'procedureId' => $procedureId,
            'statement'   => $statementId,
            'isCluster'   => $isCluster,
        ];
        $exceptionRedirectRoute = $isCluster ? 'DemosPlan_cluster_view' : 'dm_plan_assessment_single_view';
        try {
            $statementAsArray = $assessmentTableServiceOutput->singleStatementHandler(
                $statementId,
                $rParams,
                $fParams,
            );
        } catch (StatementNameTooLongException $e) {
            $this->getMessageBag()->add(
                'warning',
                'warning.limited.input.field',
                ['fieldname' => 'Name', 'limit' => $e->getMaxLength()]
            );

            return $this->redirectToRoute($exceptionRedirectRoute, $exceptionRedirectRouteParams);
        } catch (InvalidDataException) {
            $this->getMessageBag()->add('error', 'error.statement.final.send.syntax.email.cc');

            return $this->redirectToRoute($exceptionRedirectRoute, $exceptionRedirectRouteParams);
        }
        // handle all redirects
        $redirectReturn = null;
        $this->prepareHashListWithDefaults($request, $procedureId, self::HASH_TYPE_ASSESSMENT, $rParams);

        $session = $request->getSession();

        if (0 === (is_countable($statementAsArray) ? count($statementAsArray) : 0)) {
            $this->getMessageBag()->add('error', 'error.statement.not.found');

            $redirectReturn = $this->redirectToRoute(
                'dplan_assessmenttable_view_table',
                [
                    'procedureId' => $procedureId,
                    'filterHash'  => $session->get('hashList')[$procedureId]['assessment']['hash'],
                ]
            );
        }

        // ref: T7689: redirect if statement is member of cluster (after update statement)
        if (array_key_exists('clusterStatement', $statementAsArray)
            && $statementAsArray['headStatement'] instanceof Statement
        ) {
            $routeParameters = ['procedure' => $procedureId, 'statementId' => $statementId];

            $redirectReturn ??= $this->redirectToRoute('DemosPlan_cluster_single_statement_view', $routeParameters);
        }

        // refresh elasticsearch indexes to ensure that changes are shown immediately
        if ($request->request->has('submit_item_return_button') && null === $redirectReturn) {
            $this->setElasticsearchIndexManager($this->indexManager);
            $this->refreshElasticsearchIndexes();
            $hashListAssessment = $session->get('hashList')[$procedureId]['assessment'];

            $redirectReturn = $this->redirectToRoute(
                'dplan_assessmenttable_view_table',
                [
                    'procedureId' => $procedureId,
                    'filterHash'  => $hashListAssessment['hash'],
                    'page'        => $hashListAssessment['page'],
                    'r_limit'     => $hashListAssessment['r_limit'],
                    '_fragment'   => 'itemdisplay_'.$statementId,
                ]
            );
        }

        // redirect to same form to avoid sending form multiple times on reload
        // also mitigate effect that changes are not visible immediately
        if ($request->request->has('r_action') && null === $redirectReturn) {
            $redirectRoute = 'dm_plan_assessment_single_view';
            if ($isCluster) {
                $redirectRoute = 'DemosPlan_cluster_view';
            }

            $redirectReturn = $this->redirectToRoute(
                $redirectRoute,
                ['procedureId' => $procedureId, 'statement' => $statementId]
            );
        }
        if (null !== $redirectReturn) {
            return $redirectReturn;
        }
        // if no redirects prepare template for rendering
        $filterHash = $session->get('hashList')[$procedureId]['assessment']['hash'];
        $templateVars = $this->getViewSingleActionTemplateVars(
            $filterHash,
            $procedureId,
            $statementObject,
            $statementAsArray,
            $rParams['request'],
            $mapService,
            $translator,
            $currentProcedureService,
            $statementService,
            $currentUser
        );

        // reload files as the might be updated
        $templateVars['table']['statement']['files'] = $fileService->getEntityFileString(Statement::class, $statementObject->getId(), 'file');

        $template = '@DemosPlanCore/DemosPlanAssessmentTable/DemosPlan/shared/v1/assessment_statement.html.twig';
        if ($isCluster) {
            $template = '@DemosPlanCore/DemosPlanStatement/DemosPlanAssessment/cluster_detail.html.twig';
            $clusterStatements = $templateVars['table']['statement']['cluster'];
            $templateVars['table']['countOfClusterElements'] =
                $clusterStatements instanceof Collection ? $clusterStatements->count() : 0;
        }

        // We need to rebuild the EnrichDataView event

        return $this->renderTemplate(
            $template,
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => $title,
            ]
        );
    }

    /**
     * Kopieren einer einzelnen Stellungnahme.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @throws Exception
     */
    #[Route(name: 'dm_plan_assessment_single_copy', path: '/verfahren/{procedure}/abwaegung/copy/{statement}')]
    public function copySingleStatementAction(StatementService $statementService, string $procedure, string $statement): RedirectResponse
    {
        try {
            $result = $statementService->copyStatementWithinProcedure($statement);
        } catch (CopyException|ClusterStatementCopyNotImplementedException) {
            $result = false;
        }

        if ($result instanceof Statement) {
            $this->getMessageBag()->add('confirm', 'confirm.single.statement.copied');
            $copiedStatement = $result->getId();
            $redirectParameters = ['procedureId' => $procedure, 'statement' => $copiedStatement, 'title' => 'assessment.table'];
        } else {
            $redirectParameters = ['procedureId' => $procedure, 'statement' => $statement, 'title' => $statement];
        }

        // Geh auf die Detailseite der kopierten Stellungnahme
        return $this->redirectToRoute('dm_plan_assessment_single_view', $redirectParameters);
    }

    /**
     * Simply returns the text of the boilerplate that has been assigned
     * to the tag with the given id.
     * This action is used to fill out the votum-form when assigning
     * tags to them.
     *
     * @DplanPermissions("area_admin_boilerplates")
     *
     * @param string $tag
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route(name: 'dm_plan_assessment_get_boilerplates_ajax', path: '/boilerplatetext/{procedure}/{tag}', options: ['expose' => true])]
    public function getBoilerplateAjaxAction(TagService $tagService, TranslatorInterface $translator, $tag)
    {
        try {
            $err = [
                'code'    => 404,
                'success' => false,
                'body'    => '',
            ];

            $tag = $tagService->getTag($tag);

            if (null === $tag) {
                return new JsonResponse($err);
            }
            $boilerplate = $tag->getBoilerplate();
            if (null === $boilerplate) {
                $err['message'] = $translator->trans('tags.no.boilerplates');

                return new JsonResponse($err);
            }

            return new JsonResponse(
                [
                    'code'    => 100,
                    'success' => true,
                    'body'    => nl2br((string) $boilerplate->getText()),
                ]
            );
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Get complete text of a statement to be displayed in a <height-limit> component.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * Optionally pass `includeShortened=true` as get parameter to include
     * shortened fragment.
     *
     * @param string $statementId
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route(name: 'dm_plan_assessment_get_statement_ajax', path: '/_ajax/statement/{statementId}', options: ['expose' => true])]
    public function getStatementRemainderAjaxAction(Request $request, StatementService $statementService, $statementId)
    {
        try {
            /* @var Statement $statement */
            $statement = $statementService->getStatement($statementId);

            if (null === $statement) {
                // This should be a proper exception
                throw new LogicException("[Controller] No statement found for id {$statementId}.");
            }

            $data = ['original' => $statement->getText()];

            if ($request->get('includeShortened')) {
                $data['shortened'] = HTMLFragmentSlicer::getShortened($data['original']);
            }

            return $this->renderJson($data);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Get complete Recommendation of a statement to be displayed in heightLimit Filter.
     *
     * Optionally pass `includeShortened=true` as get parameter to include
     * shortened fragment.
     *
     * @DplanPermissions({"area_admin_assessmenttable", "field_statement_recommendation"})
     *
     * @param string $statementId
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route(name: 'dm_plan_assessment_get_recommendation_ajax', path: '/_ajax/recommendation/{statementId}', options: ['expose' => true])]
    public function getRecommendationRemainderAjaxAction(Request $request, StatementService $statementService, $statementId)
    {
        try {
            /* @var Statement $statement */
            $statement = $statementService->getStatement($statementId);

            if (null === $statement) {
                // This should be a proper exception
                throw new LogicException("[Controller] No statement found for id {$statementId}.");
            }

            $data = ['original' => $statement->getRecommendation()];

            if ($request->get('includeShortened')) {
                $sliced = HTMLFragmentSlicer::slice($data['original']);
                $data['shortened'] = $sliced->getShortenedFragment();
            }

            return $this->renderJson($data);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Return all versions of considerations of a fragment.
     *
     * @DplanPermissions("feature_statements_fragment_edit")
     *
     * @param bool   $isReviewer
     * @param string $fragmentId
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_assessment_fragment_get_consideration_versions', path: '/_ajax/assessment/{ident}/fragment/{fragmentId}/get', defaults: ['isReviewer' => false], options: ['expose' => true])]
    #[Route(name: 'dplan_assessment_fragment_get_consideration_versions_reviewer', path: '/_ajax/fragment/{fragmentId}/get', defaults: ['isReviewer' => true], options: ['expose' => true])]
    public function getFragmentConsiderationVersionsAjaxAction(CurrentUserInterface $currentUser, $isReviewer, $fragmentId)
    {
        try {
            $returnCode = 100;
            $success = false;

            $departmentId = $currentUser->getUser()->getDepartment()->getId();

            $this->getLogger()->debug('Get Fragment Consideration Versions as Reviewer: '.DemosPlanTools::varExport($isReviewer, true));

            $fragmentVersions = $this->statementHandler->getStatementFragmentVersions($fragmentId, $departmentId, $isReviewer);

            if (null !== $fragmentVersions) {
                $returnCode = 200;
                $success = true;
            }

            return $this->renderJson($fragmentVersions, $returnCode, $success);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * @param string      $procedureId
     * @param array       $rParams
     * @param string|null $filterHash
     */
    #[Route(name: 'dplan_assessment_table_assessment_table_statement_bulk_edit_action', path: '/verfahren/{procedureId}/bulk-edit', methods: ['GET'], options: ['expose' => true])]
    public function prepareHashListWithDefaults(Request $request, $procedureId, string $type, $rParams, $filterHash = null): void
    {
        $hashList = $request->getSession()->get('hashList', []);

        if (!array_key_exists($procedureId, $hashList)) {
            $hashList[$procedureId] = [
                'assessment' => [
                    'page'    => 1,
                    'hash'    => null,
                    'r_limit' => 25,
                ],
                'original'   => [
                    'page'    => 1,
                    'hash'    => null,
                    'r_limit' => 25,
                ],
            ];
        }

        if ($request->query->has('page')) {
            $hashList[$procedureId][$type]['page'] = $request->query->get('page');
        }

        if (array_key_exists('limit', $rParams['request'])) {
            $hashList[$procedureId][$type]['r_limit'] = $rParams['request']['limit'];
        }

        if (null !== $filterHash) {
            $hashList[$procedureId][$type]['hash'] = $filterHash;
        }

        $request->getSession()->set('hashList', $hashList);
    }

    /**
     * @DplanPermissions({"area_admin_assessmenttable", "feature_statement_bulk_edit"})
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_assessment_table_assessment_table_statement_bulk_edit_action', path: '/verfahren/{procedureId}/bulk-edit', methods: ['GET'], options: ['expose' => true])]
    public function statementBulkEditAction(FormFactoryInterface $formFactory, Request $request, string $procedureId)
    {
        $templateVars = [];
        // get authorized users
        $templateVars['authorizedUsersOfMyOrganization'] = $this->procedureService->getAuthorizedUsers(
            $procedureId
        );

        $statementBulkEditForm = $this->getForm(
            $formFactory,
            new StatementBulkEditVO($procedureId),
            StatementBulkEditType::class
        );
        $templateVars['form'] = $statementBulkEditForm->createView();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/bulk_edit_statement.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'statement.bulk.edit',
                // 'procedure' field needed for navigation on right side
                'procedure'    => $procedureId,
                'procedureId'  => $procedureId,
            ]
        );
    }

    /**
     * @DplanPermissions({"area_admin_assessmenttable", "feature_statement_fragment_bulk_edit"})
     *
     * @param string $procedureId
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_assessment_table_assessment_table_statement_fragment_bulk_edit', path: '/verfahren/{procedureId}/fragment-bulk-edit', methods: ['GET'], options: ['expose' => true])]
    public function statementFragmentBulkEditAction(Request $request, $procedureId)
    {
        $templateVars = [];
        // get authorized users
        $templateVars['authorizedUsersOfMyOrganization'] = $this->procedureService->getAuthorizedUsers(
            $procedureId
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/bulk_edit_statement_fragment.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'fragment.bulk.edit',
                // 'procedure' field needed for navigation on right side
                'procedure'    => $procedureId,
            ]
        );
    }

    /**
     * @throws Exception
     */
    protected function getViewSingleActionTemplateVars(
        $filterHash,
        string $procedureId,
        Statement $statement,
        array $statementAsArray,
        array $rParamsRequest,
        MapService $mapService,
        TranslatorInterface $translator,
        CurrentProcedureService $currentProcedureService,
        StatementService $statementService,
        CurrentUserInterface $currentUser
    ): array {
        $serviceVersion = $statementService->getVersionFields($statement);

        $templateVars = [];
        $templateVars['table'] = [
            'procedure' => $statementAsArray['procedure'],
            'statement' => $statementAsArray,
            'version'   => $serviceVersion,
        ];
        $templateVars['filterHash'] = $filterHash;

        // Verfahrensschritte
        $templateVars['internalPhases'] = $this->globalConfig->getInternalPhases();
        $templateVars['externalPhases'] = $this->globalConfig->getExternalPhases();

        $resElements = $this->statementHandler->getElementBlock($procedureId);
        if (isset($resElements['paragraph'])) {
            $templateVars['table']['paragraph'] = $resElements['paragraph'];
            // füge ggf. einen gelöschten Absatz hinzu, der dem Statement zugewiesen ist
            // Ist das Statement einem Absatz zugewiesen?
            $hasParagraph = isset($statementAsArray['paragraph'])
                && 0 < (is_countable($statementAsArray['paragraph']) ? count($statementAsArray['paragraph']) : 0);
            if ($hasParagraph) {
                $paragraphElementId = $statementAsArray['paragraph']['elementId'];
                // Hat das Element Kapitel?
                if (array_key_exists($paragraphElementId, $resElements['paragraph'])) {
                    $paragraphExists = $this->recursiveArraySearch(
                        $statementAsArray['paragraph']['ident'],
                        $resElements['paragraph'][$paragraphElementId]
                    );
                    // Ist der Absatz mittlerweile gelöscht worden?
                    if (false === $paragraphExists) {
                        $templateVars['table']['paragraphDeleted'] = $statementAsArray['paragraph'];
                    }
                }
            }
        }
        if (isset($resElements['documents'])) {
            $templateVars['table']['documents'] = $resElements['documents'];
        }
        if (isset($resElements['elements'])) {
            $templateVars['table']['elements'] = $resElements['elements'];
        }

        // wenn Rückmeldung per Post gewünscht, wird Schlussmitteilung nicht versendet
        // -->Option wird im template ausgeblendet
        $templateVars['sendFinalEmail'] = true;
        // wenn es Mitzeichner gibt, dann wird die Option "Schlussmitteilung versenden" plus Extra-Erklärung
        // im template wieder einblendet
        $templateVars['finalEmailOnlyToVoters'] = false;
        if (array_key_exists('feedback', $templateVars['table']['statement'])) {
            if ('snailmail' === $templateVars['table']['statement']['feedback']
                && empty($templateVars['table']['statement']['votes'])
            ) {
                $templateVars['table']['statement']['feedback'] = $translator->trans('via.post');
                $templateVars['sendFinalEmail'] = false;
            } elseif ('snailmail' === $templateVars['table']['statement']['feedback']
                && !empty($templateVars['table']['statement']['votes'])
            ) {
                $templateVars['table']['statement']['feedback'] = $translator->trans('via.post');
                $templateVars['sendFinalEmail'] = true;
                $templateVars['finalEmailOnlyToVoters'] = true;
            } elseif ('email' === $templateVars['table']['statement']['feedback']) {
                $templateVars['table']['statement']['feedback'] = $translator->trans('via.mail');
                $templateVars['sendFinalEmail'] = true;
            }
        }

        // Abruf der vergebenen Tags in diesem Verfahren
        if ($this->permissions->hasPermission('feature_statements_tag')) {
            $templateVars['topics'] = $this->procedureService->getTopics($procedureId);
        }

        // Ersetze die Phase, in der die SN eingegangen ist
        $templateVars['table']['statement']['phase'] =
            $statementService->getInternalOrExternalPhaseName($statementAsArray);

        // hole Infos zu den Mitzeichnern
        foreach ($templateVars['table']['statement']['votes'] as $key => $vote) {
            $voteUser = $this->userService->getSingleUser($vote['uId']);
            $templateVars['table']['statement']['votes'][$key]['user'] = $voteUser;
        }

        // hole die E-Mail-Adressen aus dem CC-feld der Organisationen
        $orgaOfSubmitter = $this->userService->getUserOrga(
            $templateVars['table']['statement']['uId']
        );

        // falls angegeben, gebe die eingetragenen E-Mail-Adressen im CC-Feld aus
        $templateVars['emailsCC'] = isset($rParamsRequest['send_emailCC']) && 0 < strlen((string) $rParamsRequest['send_emailCC'])
            ? $rParamsRequest['send_emailCC']
            : '';
        $templateVars['email2'] = '';
        if (null !== $orgaOfSubmitter) {
            // ist es eine Bürgerstellungnahme?
            if (Statement::EXTERNAL === $templateVars['table']['statement']['publicStatement']
                && isset($templateVars['table']['statement']['meta'])
            ) {
                $templateVars['email2'] = $templateVars['table']['statement']['meta']['orgaEmail'];
            } else {
                // normale TöB-Stellungnahme
                $templateVars['email2'] = $orgaOfSubmitter->getEmail2();
            }
        } elseif (0 < strlen((string) $templateVars['table']['statement']['meta']['orgaEmail'])) {
            // manuelle Stellungnahme
            $templateVars['email2'] = $templateVars['table']['statement']['meta']['orgaEmail'];
        }
        $templateVars['ccEmail2'] = null !== $orgaOfSubmitter ? $orgaOfSubmitter->getCcEmail2() : '';

        // schreibe den Veröffentlichungsstatus in die Stellungnahme
        $templateVars['table']['statement']['publicAllowed'] = $statement->getPublicAllowed();
        $templateVars['table']['statement']['publicVerified'] = $statement->getPublicVerified();
        $templateVars['table']['statement']['publicVerifiedTranslation'] = $statement->getPublicVerifiedTranslation();
        $templateVars['table']['statement']['isSubmittedByCitizen'] = $statement->isSubmittedByCitizen();

        $templateVars = $this->appendLocationFieldsToTemplateVarsIfNeeded($procedureId, $templateVars);

        // hole die Textbausteine
        $templateVars['boilerplates'] = $this->procedureService->getBoilerplatesOfCategory($procedureId, 'email');
        $templateVars['boilerplateGroups'] = $this->procedureService->getBoilerplateGroups(
            $procedureId,
            'email'
        );

        $this->addExtraItemToAssessmentTableBreadcrumbs($filterHash, $procedureId, $translator);

        $demosplanUser = $currentUser->getUser();
        $templateVars['isAdmin'] = $demosplanUser->isProcedureAdmin();
        $templateVars['adviceValues'] = $this->getFormParameter('statement_fragment_advice_values');
        $templateVars['statusValues'] = $this->getFormParameter('statement_status');
        $templateVars['priorityValues'] = $this->getFormParameter('statement_priority');

        $templateVars['formOptions']['userState'] = $this->getFormParameter('statement_user_state');
        $templateVars['formOptions']['userGroup'] = $this->getFormParameter('statement_user_group');
        $templateVars['formOptions']['userPosition'] = $this->getFormParameter('statement_user_position');

        // add current user data for assignment
        $templateVars['currentUserId'] = $demosplanUser->getId();
        $templateVars['currentUserName'] = $demosplanUser->getFullname();

        //  check if any of the related fragments have considerations set
        if ($this->permissions->hasPermission('feature_statements_fragment_consideration')) {
            $templateVars['table']['statement']['fragmentConsiderations'] = false;
            foreach ($templateVars['table']['statement']['fragments'] as $fragment) {
                if (0 < strlen((string) $fragment->getConsideration())) {
                    $templateVars['table']['statement']['fragmentConsiderations'] = true;
                    break;
                }
            }
        }

        // @improve T12687 move route to other bundle
        $templateVars['readOnly'] = $this->statementHandler->isVoteStkReadOnly(
            $statement,
            $demosplanUser->getId()
        );

        // Add map baselayers to templateVars to display map
        $currentProcedure = $currentProcedureService->getProcedureArray();
        if ($currentProcedure['isMapEnabled']
            && $this->permissions->hasPermission('area_map_participation_area')
        ) {
            $gisLayers = $mapService->getGisList($procedureId, 'base');
            $templateVars['baselayers'] = [
                'gislayerlist' => $mapService->getLayerObjects($gisLayers),
            ];
        }

        $templateVars['statementPublicationRejectionMailVars']
            = $statementService->getStatementPublicationNotificationEmailVariables($statement);

        $templateVars['fileHashToFileContainerMapping'] =
            $statementService->createFileHashToFileContainerMapping($templateVars['table']['statement']['id']);

        return $templateVars;
    }

    /**
     * @param string $procedureId
     *
     * @return array
     */
    protected function appendLocationFieldsToTemplateVarsIfNeeded($procedureId, array $templateVars)
    {
        if ($this->permissions->hasPermission('field_statement_county')) {
            $templateVars['availableCounties'] = $this->countyService->getAllCounties();
        }
        if ($this->permissions->hasPermission('field_statement_municipality')) {
            $templateVars['availableMunicipalities'] = $this->municipalityService->getAllMunicipalities();
        }
        if ($this->permissions->hasPermission('field_statement_priority_area')) {
            $templateVars['availablePriorityAreas'] = $this->priorityAreaService->getAllPriorityAreas();
        }
        if ($this->permissions->hasPermission('feature_statement_cluster')) {
            $templateVars['table']['procedure']['clusterStatements'] = $this->statementHandler->getClustersOfProcedure(
                $procedureId
            );
        }

        return $templateVars;
    }

    /**
     * @param string $procedureId
     */
    protected function addExtraItemToAssessmentTableBreadcrumbs($filterHash, $procedureId, TranslatorInterface $translator)
    {
        $this->breadcrumb->addItem(
            [
                'title' => $translator->trans('assessment.table', [], 'page-title'),
                'url'   => $this->generateUrl(
                    'dplan_assessmenttable_view_table',
                    [
                        'procedureId' => $procedureId,
                        'filterHash'  => $filterHash,
                    ]
                ),
            ]
        );
    }

    private function getSortingDirections(): array
    {
        $sortingDirections = [
            [
                'value'       => 'submitDate',
                'translation' => 'date.submitted',
            ],
            [
                'value'       => 'planningDocument',
                'translation' => 'document',
            ],
            [
                'value'       => 'institution',
                'translation' => 'submitter.invitable_institution',
            ],
            [
                'value'       => 'priority',
                'translation' => 'priority',
            ],
        ];

        // add another sort option for bobhh
        if ($this->permissions->hasPermission('feature_assessmenttable_special_sorting')) {
            $sortingDirections[] = [
                'value'       => 'forPoliticians',
                'translation' => 'forPoliticians',
            ];
        }

        return $sortingDirections;
    }
}
