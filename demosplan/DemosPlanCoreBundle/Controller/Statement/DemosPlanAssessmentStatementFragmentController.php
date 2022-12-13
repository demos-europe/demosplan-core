<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use function array_key_exists;
use function array_merge;
use function collect;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanAssessmentTableBundle\Logic\AssessmentTableServiceOutput;
use demosplan\DemosPlanAssessmentTableBundle\Logic\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NotAssignedException;
use demosplan\DemosPlanCoreBundle\Logic\SearchIndexTaskService;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Filter;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryFragment;
use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use demosplan\DemosPlanStatementBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanStatementBundle\Logic\AssessmentHandler;
use demosplan\DemosPlanStatementBundle\Logic\CountyService;
use demosplan\DemosPlanStatementBundle\Logic\MunicipalityService;
use demosplan\DemosPlanStatementBundle\Logic\PriorityAreaService;
use demosplan\DemosPlanStatementBundle\Logic\StatementFragmentService;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserService;
use Exception;

use function http_build_query;
use function is_array;
use function str_replace;
use function strlen;
use function strpos;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class DemosPlanAssessmentStatementFragmentController
 * Statement Fragment specific methods.
 */
class DemosPlanAssessmentStatementFragmentController extends DemosPlanAssessmentController
{
    /**
     * @var StatementHandler
     */
    private $statementHandler;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    public function __construct(PermissionsInterface $permissions, StatementHandler $statementHandler)
    {
        $this->permissions = $permissions;
        $this->statementHandler = $statementHandler;
        parent::__construct($permissions);
    }

    /**
     * Fragment Statement into multiple slices.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment",
     *     path="/verfahren/{procedure}/fragment/{statementId}",
     *     options={"expose": true}
     * )
     * @DplanPermissions({"area_admin_assessmenttable", "feature_statements_fragment_add"})
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @throws Exception
     */
    public function fragmentStatementAction(
        CountyService $countyService,
        MunicipalityService $municipalityService,
        PriorityAreaService $priorityAreaService,
        Request $request,
        StatementHandler $statementHandler,
        string $statementId,
        string $procedure
    ) {
        try {
            $templateVars = [];
            $procedureId = $procedure;
            $statement = $statementHandler->getStatement($statementId);

            if (null === $statement) {
                $this->getMessageBag()->add('error', 'error.statement.not.found');

                return $this->redirectToRoute('dplan_assessmenttable_view_table', [
                    'procedureId' => $procedureId,
                    'filterHash'  => $request->getSession()->get('filterHash'),
                ]);
            }
            $procedure = $statement->getProcedure();

            $templateVars['statement'] = $statement;
            $templateVars['existingFragmentsList'] = $statementHandler->getStatementFragmentsStatement($statementId);
            $templateVars['topics'] = $statementHandler->getTopicsByProcedure($procedureId);

            // @improve T14122
            if ($this->permissions->hasPermission('field_statement_county')) {
                $templateVars['availableCounties'] = $countyService->getAllCounties();
            }
            if ($this->permissions->hasPermission('field_statement_municipality')) {
                $templateVars['availableMunicipalities'] = $municipalityService->getAllMunicipalities();
            }
            if ($this->permissions->hasPermission('field_statement_priority_area')) {
                $templateVars['availablePriorityAreas'] = $priorityAreaService->getAllPriorityAreas();
            }

            $resElements = $statementHandler->getElementBlock($procedureId);

            if (isset($resElements['documents'])) {
                $templateVars['documents'] = $resElements['documents'];
            }
            if (isset($resElements['elements'])) {
                $templateVars['elements'] = $resElements['elements'];
            }
            if (isset($resElements['paragraph'])) {
                $templateVars['paragraph'] = $resElements['paragraph'];
            }

            $templateVars['availableTags'] = $statementHandler->getTopicsAndTagsOfProcedureAsArray($procedureId);
            $templateVars['statementFragmentAgencies'] = $statementHandler->getAgencyData();

            $newFragment = new StatementFragment();
            $newFragment->setCounties($statement->getCounties()->toArray());
            $newFragment->setMunicipalities($statement->getMunicipalities()->toArray());
            $newFragment->setPriorityAreas($statement->getPriorityAreas()->toArray());
            $newFragment->setTags($statement->getTags()->toArray());
            $templateVars['fragment'] = $newFragment;
            $templateVars['filterHash'] = $request->getSession()->get('filterHash', null);
            $templateVars['procedure'] = $procedure;

            return $this->renderTemplate(
                '@DemosPlanStatement/DemosPlanStatement/fragment_statement.html.twig',
                [
                    'templateVars' => $templateVars,
                    'procedure'    => $procedureId,
                    'title'        => 'assessment.table.statements.fragment',
                ]
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Return all data necessary to display a list that contains all
     * statement fragment versions related to a department.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment_list_fragment_archived_reviewer",
     *     path="/datensatz/liste/archive"
     * )
     *  @DplanPermissions({"area_statement_fragments_department_archive","feature_statements_fragment_list"})
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    public function getStatementFragmentListArchiveAction(CurrentUserService $currentUser, Request $request, RouterInterface $router, TranslatorInterface $translator)
    {
        $templateVars = [];
        $statementHandler = $this->statementHandler;
        $statementHandler->getEsQueryFragment()->setScope(QueryFragment::SCOPE_PLANNER);
        $templateVars['agencies'] = [];
        $pagerQuerystring = collect($request->query->all())->only(['r_limit', 'page'])->all();
        $templateVars['formAction'] = $router->generate('DemosPlan_statement_fragment_update_redirect_fragment_reviewer').'?'.http_build_query($pagerQuerystring);

        $requestPost = $this->rememberFilters($request);
        $statementHandler->setRequestValues($requestPost);

        $departmentId = $currentUser->getUser()->getDepartmentId();

        // liefert fuer jedes fragment des departments die letzte version:
        $result = $statementHandler->getStatementFragmentsDepartmentArchive($departmentId);

        $templateVars['list'] = $result;
        $templateVars['totalResults'] = count($result);
        $templateVars['isArchive'] = true;

        $templateVars['adviceValues'] = $this->getFormParameter('statement_fragment_advice_values');

        $templateVars['filterName'] = [
            'priorityAreaKeys'  => $translator->trans('priorityArea'),
            'countyNames'       => $translator->trans('county'),
            'municipalityNames' => $translator->trans('municipality'),
            'planningDocument'  => $translator->trans('document'),
            'reasonParagraph'   => $translator->trans('paragraph'),
            'topicNames'        => $translator->trans('topic'),
            'tagNames'          => $translator->trans('tag'),
            'procedureId'       => $translator->trans('procedure'),
            'voteAdvice'        => $translator->trans('fragment.voteAdvice.short'),
        ];

        $templateVars['definition'] = $statementHandler->getEsQueryFragment();

        // <temporaryHack>
        // This hack should be temporary until filters are fetched via ajax
        $esQueryFragment = $statementHandler->getEsQueryFragment();
        /** @var Filter[][] $filters */
        $filters = $esQueryFragment->getFilters();
        $interfaceFilters = $esQueryFragment->getInterfaceFilters();

        $filterSet = [];
        foreach ($filters as $activeFilters) {
            foreach ($activeFilters as $activeFilter) {
                $activeInterfaceFilter = collect($interfaceFilters)->filter(
                    function ($interfaceFilter) use ($activeFilter) {
                        /* @var FilterDisplay $interfaceFilter */
                        return $interfaceFilter->getName() == $activeFilter->getField();
                    }
                )->first();
                if ($activeInterfaceFilter instanceof FilterDisplay) {
                    $filterSet[] = $activeInterfaceFilter->getTitleKey();
                }
            }
        }

        $templateVars['filterSet']['activeFilters'] = $filterSet;
        // </temporaryHack>

        return $this->renderTemplate(
            '@DemosPlanStatement/DemosPlanStatement/list_statement_fragments_archive.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'fragments.list.archive',
            ]
        );
    }

    /**
     * Return all data necessary to display a list that contains all
     * statement fragments related to a department.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment_list_fragment_reviewer",
     *     path="/datensatz/liste",
     *     options={"expose": true}
     * )
     *  @DplanPermissions({"area_statement_fragments_department","feature_statements_fragment_list"})
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @throws Exception
     *
     * @internal param string $type
     */
    public function getStatementFragmentListAction(
        CountyService $countyService,
        CurrentUserService $currentUser,
        MunicipalityService $municipalityService,
        PriorityAreaService $priorityAreaService,
        Request $request,
        RouterInterface $router
    ) {
        $pagerQuerystring = collect($request->query->all())->only(['r_limit', 'page'])->all();
        $templateVars = [];
        $statementHandler = $this->statementHandler;
        $statementHandler->getEsQueryFragment()->setScope(QueryFragment::SCOPE_PLANNER);

        $templateVars['agencies'] = [];
        $templateVars['formAction'] = $router->generate('DemosPlan_statement_fragment_update_redirect_fragment_reviewer').'?'.http_build_query($pagerQuerystring);

        $requestPost = $this->rememberFilters($request);
        $departmentId = $currentUser->getUser()->getDepartmentId();

        // Replacing '_' with '.' to get valid filter names
        foreach ($requestPost as $filterName => $value) {
            $requestPost[str_replace('_', '.', $filterName)] = $value;
        }

        $statementHandler->setRequestValues($requestPost);
        $result = $statementHandler->getStatementFragmentsDepartment($departmentId);

        $templateVars['list'] = $result;
        $templateVars['totalResults'] = count($result);
        /* There is no paginator right now. Limit is set to 3000 in FragmentElasicsearchRepository->getResult() */
        $templateVars['limitResults'] = 3000;

        $templateVars['adviceValues'] = $this->getFormParameter('statement_fragment_advice_values');

        if ($this->permissions->hasPermission('field_statement_county')) {
            $templateVars['availableCounties'] = Json::encode($countyService->getAllCountiesAsArray());
        }
        if ($this->permissions->hasPermission('field_statement_municipality')) {
            $templateVars['availableMunicipalities'] = Json::encode(
                $municipalityService->getAllMunicipalitiesAsArray()
            );
        }
        if ($this->permissions->hasPermission('field_statement_priority_area')) {
            $templateVars['availablePriorityAreas'] = Json::encode(
                $priorityAreaService->getAllPriorityAreasAsArray()
            );
        }

        // add current user data for assignment
        $templateVars['currentUserId'] = $currentUser->getUser()->getId();
        $templateVars['currentUserName'] = $currentUser->getUser()->getFullname();

        $templateVars['definition'] = $statementHandler->getEsQueryFragment();

        // <temporaryHack>
        // This hack should be temporary until filters are fetched via ajax
        $esQueryFragment = $statementHandler->getEsQueryFragment();
        /** @var Filter[][] $filters */
        $filters = $esQueryFragment->getFilters();
        $interfaceFilters = $esQueryFragment->getInterfaceFilters();

        $filterSet = [];
        foreach ($filters as $activeFilters) {
            foreach ($activeFilters as $activeFilter) {
                $activeInterfaceFilter = collect($interfaceFilters)->filter(
                    function ($interfaceFilter) use ($activeFilter) {
                        /* @var FilterDisplay $interfaceFilter */
                        return $interfaceFilter->getName() === $activeFilter->getField();
                    }
                )->first();
                if ($activeInterfaceFilter instanceof FilterDisplay) {
                    $filterSet[] = $activeInterfaceFilter->getTitleKey();
                }
            }
        }

        $templateVars['filterSet']['activeFilters'] = $filterSet;
        // </temporaryHack>

        return $this->renderTemplate(
            '@DemosPlanStatement/DemosPlanStatement/list_statement_fragments.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'fragments.list',
            ]
        );
    }

    /**
     * Edit a single fragment.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment_edit_ajax",
     *     path="/_ajax/procedure/{procedure}/fragment/{fragmentId}/edit",
     *     options={"expose": true}
     * )
     * @Route(
     *     name="DemosPlan_statement_fragment_edit_reviewer_ajax",
     *     path="/_ajax/fragment/{fragmentId}/reviewer/edit",
     *     defaults={"isReviewer": true},
     *     options={"expose": true}
     * )
     * @DplanPermissions("feature_statements_fragment_edit")
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editStatementFragmentAjaxAction(
        CurrentUserService $currentUser,
        Request $request,
        SearchIndexTaskService $searchIndexTaskService,
        StatementFragmentService $statementFragmentService,
        string $fragmentId,
        bool $isReviewer = false)
    {
        try {
            // Route is called from planner and reviewer
            $requestPost = $request->request->all();
            $assignedTo = $statementFragmentService->getAssigneeOfFragment($fragmentId);

            // block changing fragment if locked by another user + create message:
            if (null === $assignedTo || $assignedTo->getId() !== $currentUser->getUser()->getId()) {
                $name = $assignedTo instanceof User ? $assignedTo->getName() : '';
                $this->getMessageBag()->add(
                    'warning', 'warning.fragment.needLock',
                    ['name' => $name]
                );

                return $this->renderJson([], 500, false, 500);
            }

            $namespacedParams = $this->transformRequestVariables($requestPost);
            $updateData = $namespacedParams[$fragmentId];
            // add archived user name
            $user = $currentUser->getUser();
            $updateData['r_departmentName'] = $user->getDepartmentNameLegal();
            $updateData['r_orgaName'] = $user->getOrganisationNameLegal();
            $updateData['r_currentUserName'] = $user->getFirstname().' '.$user->getLastname();
            // Reviewers may not change metadata
            if ($isReviewer) {
                $updateData['mayChangeMetaData'] = false;
            }
            $updatedStatementFragment = $this->statementHandler->updateStatementFragment($fragmentId, $updateData, $isReviewer);
            $returnCode = 200;
            $success = true;

            // as fragments are fetched from ES later on to get current structure
            // ES needs to be indexed beforehand
            $searchIndexTaskService->refreshIndex(StatementFragment::class);

            if (false === ($updatedStatementFragment instanceof StatementFragment)) {
                $this->getLogger()->error(
                    "Failed updating statement-fragment {$fragmentId}"
                );
                throw new Exception('Fragment could not be updated');
            }

            // return current Version. Use ES to receive defined structure
            $fragment = $this->statementHandler->getFragmentOfStatementES($updatedStatementFragment->getStatementId(), $fragmentId);

            return $this->renderJson($fragment, $returnCode, $success);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Delete a single fragment.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment_delete_ajax",
     *     path="/_ajax/procedure/{procedureId}/statement/{statementId}/fragment/{fragmentId}/delete",
     *     methods={"POST"},
     *     options={"expose": true}
     * )
     *  @DplanPermissions({"area_admin_assessmenttable","feature_statements_fragment_edit"})
     *
     * @param string $fragmentId
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function deleteFragmentStatementAjaxAction(Request $request, $fragmentId)
    {
        try {
            // Save statementFragment values
            $deleted = false;
            $returnCode = 100;

            try {
                $deleted = $this->statementHandler->deleteStatementFragment($fragmentId);
                $returnCode = 200;
            } catch (EntityIdNotFoundException $e) {
                $this->getMessageBag()->add('warning', 'warning.fragment.notfound');
            } catch (LockedByAssignmentException $e) {
                $this->getMessageBag()->add(
                    'warning', 'warning.delete.fragment.because.of.assignment');
            } catch (Exception $e) {
                $deleted = false;
                $returnCode = 100;
            }

            if (false === $deleted) {
                $this->getLogger()->error(
                    "Failed deleting statement-fragment {$fragmentId}"
                );
                $returnCode = 100;
            }

            return new JsonResponse(
                [
                    'code'    => $returnCode,
                    'success' => $deleted,
                ]
            );
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Return fragment data as json.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment_get_ajax",
     *     path="/_ajax/procedure/{procedure}/statement/{statementId}/fragment/{fragmentId}",
     *     options={"expose": true}
     * )
     * @DplanPermissions("area_statements_fragment")
     *
     * @param string $procedure
     * @param string $statementId
     * @param string $fragmentId
     *
     * @return JsonResponse
     */
    public function getFragmentAjaxAction(Request $request, $procedure, $statementId, $fragmentId)
    {
        try {
            $fragment = $this->statementHandler->getFragmentOfStatementES($statementId, $fragmentId);

            return $this->renderJson($fragment);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Return all considerations of all fragments of a statement.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment_considerations_get_ajax",
     *     path="/_ajax/procedure/{procedure}/statement/{statementId}/fragmentconsiderations",
     *     options={"expose": true}
     * )
     *  @DplanPermissions({"area_admin_assessmenttable","area_statements_fragment"})
     *
     * @param string $statementId
     *
     * @return JsonResponse
     */
    public function getFragmentConsiderationsAjaxAction($statementId)
    {
        try {
            $fragments = $this->statementHandler->getStatementFragmentsStatementES($statementId, []);

            // get fragment considerations
            $considerations = collect($fragments->getResult())
                ->map(function ($item) { // reduce array for considerations
                    return $item['consideration'];
                })
                ->filter(function ($item) { // values should not be empty
                    return 0 < strlen($item);
                })
                ->values()
                ->toArray();

            $data = [
                'code'    => 200,
                'success' => true,
                'body'    => ['considerations' => $considerations],
            ];

            return new JsonResponse($data);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Fragment Statement into multiple slices.
     *
     * @DplanPermissions({"area_statements_fragment", "feature_statements_fragment_add"})
     * @Route(
     *     name="DemosPlan_statement_fragment_add",
     *     path="/verfahren/{procedure}/fragment/{statementId}/add"
     * )
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @throws Exception
     */
    public function addFragmentStatementAction(
        CurrentUserInterface $currentUser,
        Request $request,
        StatementHandler $statementHandler,
        string $procedure,
        string $statementId
    ) {
        try {
            $postRequest = $request->request;

            $returnResponse = function ($procedure, $statementId) {
                return $this->redirectToRoute('DemosPlan_statement_fragment', [
                    'procedure'   => $procedure,
                    'statementId' => $statementId,
                ]);
            };

            $statement = $statementHandler->getStatement($statementId);

            if (null === $statement) {
                $this->getMessageBag()->add('error', 'error.statement.not.found');

                return $returnResponse($procedure, $statementId);
            }

            try {
                $statementFragmentData = $postRequest->all();
                $statementFragmentData['statementId'] = $statementId;
                $statementFragmentData['procedureId'] = $procedure;

                $user = $currentUser->getUser();
                if ($user instanceof User) {
                    $statementFragmentData['modifiedByUserId'] = $user->getId();
                    $statementFragmentData['modifiedByDepartmentId'] = $user->getDepartment()->getId();
                }

                // enable $propagateTags by default and disable it if the user has the permissions to
                // disable propagation and did use this permission to not request the propagation
                $propagateTags = true;
                if ($this->permissions->hasPermission('feature_optional_tag_propagation')) {
                    $propagateTags = array_key_exists('r_forwardTagsToStatements', $statementFragmentData)
                        && 'on' === $statementFragmentData['r_forwardTagsToStatements'];
                }

                try {
                    $statementFragment = $statementHandler->createStatementFragment($statementFragmentData, $propagateTags);
                } catch (NotAssignedException $e) {
                    $this->getLogger()->error("Failed creating statement-fragment {$e}");

                    return $this->redirectToRoute('core_home_loggedin');
                }

                if (null === $statementFragment) {
                    $this->getMessageBag()->add('error', 'error.statement.fragment.create');
                    $this->getLogger()->error('Created fragment is null');

                    return $returnResponse($procedure, $statementId);
                }
            } catch (Exception $e) {
                $this->getMessageBag()->add('error', 'error.statement.fragment.create');
                $this->getLogger()->error("Failed creating statement-fragment {$e}");

                return $returnResponse($procedure, $statementId);
            }

            $this->getMessageBag()->add('confirm', 'confirm.statement.fragment.created');

            return $returnResponse($procedure, $statementId);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Set a vote or advice to a fragment statement.
     *
     * @Route(
     *     name="DemosPlan_statement_fragment_update_redirect_fragment_reviewer",
     *     path="/datensatz/update/reviewer",
     *     defaults={"isReviewer": true}
     * )
     * @Route(
     *     name="DemosPlan_statement_fragment_update_redirect",
     *     path="/datensatz/update",
     * )
     *  @DplanPermissions({"area_statements_fragment","feature_statements_fragment_edit"})
     *
     * @param bool $isReviewer
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @throws Exception
     */
    public function updateStatementFragmentAction(
        CurrentUserService $currentUser,
        StatementFragmentService $statementFragmentService,
        Request $request,
        $isReviewer = false
    ) {
        $data = $this->transformRequestVariables($request->request->all());

        $anchor = '';
        $user = $currentUser->getUser();

        // only if a vote_Advice is given: save departmentName and OrgaName of current user
        foreach ($data as $ident => $voteData) {
            $voteData['r_departmentName'] = $user->getDepartmentNameLegal();
            $voteData['r_orgaName'] = $user->getOrganisationNameLegal();
            $voteData['r_currentUserName'] = $user->getFirstname().' '.$user->getLastname();

            // Reviewers may not change metadata
            if ($isReviewer) {
                $voteData['mayChangeMetaData'] = false;
            }

            $lockedForCurrentUser = false;
            $assignedTo = $statementFragmentService->getAssigneeOfFragment($ident);

            // block changing fragment if locked by another user + create message:
            if (null !== $assignedTo && $assignedTo->getId() !== $user->getId()) {
                $this->getMessageBag()->add(
                    'warning',
                    'warning.fragment.needLock',
                    ['name' => $assignedTo->getName()]
                );
                $lockedForCurrentUser = true;
            }

            $result = null;
            if (false === $lockedForCurrentUser) {
                $result = $this->statementHandler->updateStatementFragment($ident, $voteData, $isReviewer);
            }

            if (!($result instanceof StatementFragment)) {
                $this->getLogger()->error("Failed updating statement-fragment {$ident}");
                continue;
            }

            $anchor = $result->getId();
        }

        // add pagerinfo if available
        $pagerQuery = collect($request->query->all())->only(['r_limit', 'page'])->toArray();
        $redirectUrl = $this->generateUrl(
            'DemosPlan_statement_fragment_list_fragment_reviewer',
            array_merge(['_fragment' => $anchor], $pagerQuery)
        );

        if (false === $isReviewer) {
            $procedureId = $request->query->get('procedure');
            $redirectUrl = $this->generateUrl(
                'dplan_assessmenttable_view_table',
                array_merge(
                    [
                        'procedureId' => $procedureId,
                        'filterHash'  => $request->getSession()->get('filterHash', null),
                        '_fragment'   => $anchor,
                    ],
                    $pagerQuery
                )
            );
        }

        return $this->redirect($redirectUrl);
    }

    // @improve T14122

    /**
     * Returns fragment data for a statement on the assessment table.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     * @Route(
     *     name="DemosPlan_assessment_statement_fragments_ajax",
     *     path="/_ajax/assessment/{procedureId}/{statementId}",
     *     options={"expose": true}
     * )
     */
    public function assessmentStatementFragmentsAjaxAction(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        HashedQueryService $filterSetService,
        Request $request,
        StatementHandler $statementHandler,
        StatementService $statementService,
        string $procedureId,
        string $statementId
    ): JsonResponse {
        try {
            $rParams = $assessmentTableServiceOutput->getFormValues($request->request->all());
            $filteredRParams = $request->getSession()->get('assessmentTableParams:'.$procedureId, $rParams);

            // do we have stored filters?
            if ($filteredRParams !== $rParams) {
                $rParams = $filteredRParams;
            }
            $hashList = $request->getSession()->get('hashList');
            $hash = $hashList[$procedureId]['assessment']['hash'];
            $filterSet = $filterSetService->findHashedQueryWithHash($hash);
            if (null === $filterSet) {
                $request->request->set('filters', []);
                $request->request->set('search_fields', []);
                $request->request->set('search_word', '');
                $request->request->set('sort', 'submitDate:desc');
                $viewModeString = $this->globalConfig->getAssessmentTableDefaultViewMode();
                $request->request->set('view_mode', $viewModeString);

                $filterSet = $assessmentHandler->handleFilterHash($request, $procedureId);
            }

            /** @var AssessmentTableQuery $assessmentQuery */
            $assessmentQuery = $filterSet->getStoredQuery();

            $filters = $rParams['filters'];
            if (!is_array($filters)) {
                $filters = [];
            }
            $filters = array_merge($filters, $assessmentQuery->getFilters());

            $elasticsearchResultSetAllFragments = $statementHandler->getStatementFragmentsStatementES($statementId, []);
            $elasticsearchResultSetFilteredFragments = $statementHandler->getStatementFragmentsStatementES(
                $statementId,
                $statementService->mapRequestFiltersToESFragmentFilters($filters),
                $assessmentQuery->getSearchWord()
            );

            $allFragments = $assessmentHandler->sortFragmentArraysBySortIndex($elasticsearchResultSetAllFragments->getResult());
            $filteredFragments = $assessmentHandler->sortFragmentArraysBySortIndex($elasticsearchResultSetFilteredFragments->getResult());

            $data = [
                'fragments'         => $allFragments,
                'filteredFragments' => $filteredFragments,
                'statement'         => $allFragments[0]['statement'] ?? [],
            ];

            return $this->renderJson($data);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Exports a subset of fragments from the fragmentList to PDF.
     *
     * @Route(
     *     name="DemosPlan_fragment_list_export",
     *     path="/datensatz/liste/export",
     *     options={"expose": true}
     * )
     * @DplanPermissions("area_statements_fragment")
     *
     * @param Request $request ;
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws MessageBagException
     */
    public function exportFragmentListAction(CurrentUserService $currentUser, Request $request, TranslatorInterface $translator)
    {
        $vars = $request->request->all();
        $fragmentIds = [];
        if (array_key_exists('fragmentIds', $vars)) {
            $fragmentIds = $vars['fragmentIds'];
        }
        $isArchive = array_key_exists('isArchive', $vars);
        $filter = [];
        foreach ($vars as $key => $value) {
            if (0 === strpos($key, 'filter_')) {
                $filter[preg_replace('/^filter_/', '', $key)] = $value;
            }
        }

        $departmentId = $currentUser->getUser()->getDepartmentId();
        $pdf = $this->statementHandler->generateFragmentPdf($fragmentIds, null, $departmentId, $isArchive, $filter);

        $response = new Response($pdf->getContent(), 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $this->generateDownloadFilename($translator->trans('fragments.export.pdf.file.name')));

        return $response;
    }
}
