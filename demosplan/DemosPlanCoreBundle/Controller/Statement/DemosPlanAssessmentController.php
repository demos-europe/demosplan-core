<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CountyService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\MunicipalityService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\PriorityAreaService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\SubmitterValueObject;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function array_merge;
use function strcmp;
use function usort;

/**
 * Generate the views for the assessment process.
 */
class DemosPlanAssessmentController extends BaseController
{
    public function __construct(private readonly PermissionsInterface $permissions)
    {
    }

    /**
     * Sets the assignee of the given statement.
     *
     * NOTE: Only used by Statement Detail View
     *
     * @DplanPermissions("feature_statement_assignment")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_assessment_set_statement_assignment', path: '/assignment/statement/{entityId}/{assignOrUnassign}')]
    public function setStatementAssigneeAction(
        CurrentUserService $currentUser,
        Request $request,
        StatementHandler $statementHandler,
        UserService $userService,
        string $entityId,
        string $assignOrUnassign = 'assign'
    ): Response {
        $statementToUpdate = $statementHandler->getStatement($entityId);

        if (null === $statementToUpdate) {
            $this->getMessageBag()->add('error', 'error.statement.assignment.assigned');
            $this->getLogger()->warning('Could not find Statement Id '.$entityId);

            return $this->redirectBack($request);
        }

        $user = null;
        if ('assign' === $assignOrUnassign) {
            $user = $currentUser->getUser();
            $user = $userService->getSingleUser($user->getIdent());
        }

        // without report!:
        $statementHandler->setAssigneeOfStatement($statementToUpdate, $user);

        $assignee = $statementToUpdate->getAssignee();

        if ('assign' === $assignOrUnassign) {
            $this->getMessageBag()->add(
                'confirm',
                'confirm.statement.assignment.assigned',
                ['name' => $assignee->getName()]
            );
        } elseif ('unassign' === $assignOrUnassign) {
            $this->getMessageBag()->add(
                'confirm',
                'confirm.statement.assignment.unassigned'
            );
        }

        return $this->redirectBack($request, "#itemdisplay_{$entityId}");
    }

    /**
     * @DplanPermissions("feature_statement_data_input_orga")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_orga_list', path: '/statement/manual/list/{procedureId}')]
    public function getOrgaStatementListAction(CurrentUserService $currentUser, StatementHandler $statementHandler, string $procedureId): Response
    {
        $organisationId = $currentUser->getUser()->getOrganisationId();
        $statements = $statementHandler->getStatementsOfProcedureAndOrganisation(
            $procedureId,
            $organisationId
        );

        $templateVars = [
            'statements'  => $statements,
            'count'       => is_countable($statements) ? count($statements) : 0,
            'orgaId'      => $organisationId,
            'procedureId' => $procedureId,
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/list_orga_statements.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'statements',
                'procedureId'  => $procedureId,
            ]
        );
    }

    /**
     * Create new Statement.
     *
     * @DplanPermissions("feature_statement_data_input_orga")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_new_submitted', path: '/statement/new/manual/{procedureId}', options: ['expose' => true])]
    public function newManualStatementAction(
        CurrentUserService $currentUser,
        FileUploadService $fileUploadService,
        Request $request,
        ServiceOutput $serviceOutput,
        StatementHandler $statementHandler,
        StatementService $statementService,
        AssessmentHandler $assessmentHandler,
        string $procedureId
    ): ?Response {
        $rParams = $request->request->all();
        $fParams = $fileUploadService->prepareFilesUpload($request, 'r_upload');

        if (array_key_exists('r_action', $rParams) && 'new' === $rParams['r_action']) {
            try {
                if (null !== $fParams && '' !== $fParams) {
                    $rParams['fileupload'] = $fParams;
                }

                $attachment = $fileUploadService->prepareFilesUpload($request, 'r_attachment_original');
                if ('' !== $attachment) {
                    $rParams['originalAttachments'] = [$attachment];
                }

                // T8317:
                if (array_key_exists('r_tags', $rParams) && \is_array($rParams['r_tags'])) {
                    $rParams['r_recommendation'] = $statementHandler->addBoilerplatesOfTags($rParams['r_tags']);
                }

                // T14715: avoid attach original STN to cluster
                $headStatement = null;
                if (array_key_exists('r_head_statement', $rParams) && '' !== $rParams['r_head_statement']) {
                    $headStatement = $statementHandler->getStatement($rParams['r_head_statement']);
                    unset($rParams['r_head_statement']);
                }

                $isDataInput = $currentUser->getUser()->hasRole(Role::PROCEDURE_DATA_INPUT);
                $newStatement = $statementHandler->newStatement($rParams, $isDataInput);

                if (null !== $headStatement) {
                    $statementHandler->addStatementToCluster($headStatement, $newStatement->getChildren()[0], true, true);
                }
                if ($newStatement instanceof Statement) {
                    return $this->redirectToRoute(
                        'DemosPlan_statement_new_submitted',
                        ['procedureId' => $procedureId]
                    );
                }
            } catch (InvalidArgumentException) {
                // some data is missing, process with existing requestdata
            }
        }

        $templateVars = $this->generateNewStatementTemplateVars($procedureId, $serviceOutput, $statementHandler, $statementService);

        // atm use Template from DemosPlanAssessmentTableBundle as refactoring it to this Bundle
        // generates quite a hassle as it needs to be done in all projects
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanAssessmentTable/DemosPlan/dhtml/v1/assessment_table_new_statement.html.twig',
            [
                'procedure'    => $procedureId,
                'templateVars' => $templateVars,
                'title'        => 'statement.new',
            ]
        );
    }

    /**
     * @DplanPermissions("feature_statement_data_input_orga")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_single_view', path: 'procedure/{procedureId}/statement/{statementId}/dataInput')]
    public function viewSingleStatementAction(
        Breadcrumb $breadcrumb,
        ProcedureService $procedureService,
        StatementHandler $statementHandler,
        TranslatorInterface $translator,
        string $procedureId,
        string $statementId
    ): Response {
        $statement = $statementHandler->getStatementWithCertainty($statementId);
        $procedure = $procedureService->getProcedureWithCertainty($procedureId);

        $templateVars = [];
        $templateVars['table']['statement'] = $statement;
        $templateVars['table']['procedure'] = $procedure;

        $title = 'statement.view';
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('procedure.admin.list', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_procedure_list_data_input_orga_procedures'),
            ]
        )->addItem(
            [
                'title' => $procedure->getName(),
                'url'   => $this->generateUrl(
                    'DemosPlan_statement_orga_list',
                    ['procedureId' => $procedureId]
                ),
            ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/DemosPlanAssessment/view_statement.html.twig',
            compact('title', 'templateVars')
        );
    }

    /**
     * Display single Statement cluster.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_cluster_single_statement_view', path: '/verfahren/{procedure}/cluster/statement/{statementId}')]
    public function viewStatementClusterSingleStatementAction(StatementHandler $statementHandler, string $statementId): Response
    {
        $statement = $statementHandler->getStatement($statementId);
        $templateVars = [];
        $templateVars['table']['statement'] = $statement;
        $templateVars['table']['procedure'] = $statement->getProcedure();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/DemosPlanAssessment/view_statement.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'statement.view',
            ]
        );
    }

    /**
     * Detaches a single Statement from his cluster.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_cluster_detach_statement', path: '/verfahren/{procedure}/cluster/statement/{statementId}/detach')]
    public function detachStatementFromClusterAction(StatementHandler $statementHandler, string $statementId): Response
    {
        try {
            $statementToDetach = $statementHandler->getStatement($statementId);
            $procedureId = $statementToDetach->getProcedureId();

            $statementHandler->detachStatementFromCluster($statementToDetach);

            return $this->redirectToRoute(
                'dplan_assessmenttable_view_table',
                \compact('procedureId')
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Resolves a single statementCluster.
     * All statements in the cluster will be detached.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_cluster_resolve', path: '/verfahren/{procedure}/cluster/resolve/{headStatementId}')]
    public function resolveClusterAction(StatementHandler $statementHandler, string $headStatementId): Response
    {
        try {
            $clusterToResolve = $statementHandler->getStatement($headStatementId);
            $procedureId = $clusterToResolve->getProcedureId();

            $statementHandler->resolveCluster($clusterToResolve);

            return $this->redirectToRoute(
                'dplan_assessmenttable_view_table',
                \compact('procedureId')
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Returns the base data for Vue components on the assessment table.
     *
     * @DplanPermissions("feature_procedure_get_base_data")
     */
    #[Route(name: 'DemosPlan_assessment_base_ajax', path: '/_ajax/assessment/{procedureId}', options: ['expose' => true])]
    public function assessmentBaseAjaxAction(
        CurrentUserService $currentUser,
        CountyService $countyService,
        MunicipalityService $municipalityService,
        PermissionsInterface $permissions,
        PriorityAreaService $priorityAreaService,
        ProcedureService $procedureService,
        StatementHandler $statementHandler,
        string $procedureId): JsonResponse
    {
        $data = [
            'adviceValues'      => $this->getFormParameter('statement_fragment_advice_values'),
            'tags'              => $statementHandler->getTopicsAndTagsOfProcedureAsArray($procedureId),
            'agencies'          => $statementHandler->getAgencyData(false),
            'defaultToggleView' => $this->globalConfig->getAssessmentTableDefaultToggleView(),
        ];

        // add base data for location information
        if ($permissions->hasPermission('field_statement_county')) {
            $data['counties'] = $countyService->getAllCountiesAsArray();
        }

        if ($permissions->hasPermission('field_statement_municipality')) {
            $data['municipalities'] = $municipalityService->getAllMunicipalitiesAsArray();
        }

        if ($permissions->hasPermission('field_statement_priority_area')) {
            $data['priorityAreas'] = $priorityAreaService->getAllPriorityAreasAsArray();
        }

        if ($permissions->hasPermission('field_statement_priority')) {
            $data['priorities'] = $this->getFormParameter('statement_priority');
        }

        if ($permissions->hasPermission('field_statement_status')) {
            $data['status'] = $this->getFormParameter('statement_status');
        }

        if ($permissions->hasPermission('field_fragment_status')) {
            $data['fragmentStatus'] = $this->getFormParameter('fragment_status');
        }

        // Verfahrensschritte
        $data['internalPhases'] = $this->globalConfig->getInternalPhases();
        $data['externalPhases'] = $this->globalConfig->getExternalPhases();

        $resElements = $statementHandler->getElementBlock($procedureId);
        $data['elements'] = $resElements['elements'] ?? [];
        $data['paragraph'] = $resElements['paragraph'] ?? [];
        $data['documents'] = $resElements['documents'] ?? [];

        // Get a procedure list to let user decide where to move a statement
        // Also check authentication
        if ($permissions->hasPermission('feature_statement_move_to_procedure')) {
            $data['accessibleProcedures'] = $procedureService->getAccessibleProcedureIds($currentUser->getUser(), $procedureId);

            if ($permissions->hasPermission('feature_statement_move_to_foreign_procedure')) {
                $data['inaccessibleProcedures'] = $procedureService->getInaccessibleProcedureIds($currentUser->getUser());
            }
        }

        // Get a procedure list to let user decide where to copy a statement
        // Also check authentication
        if ($permissions->hasPermission('feature_statement_copy_to_procedure')) {
            $data['accessibleProcedures'] = $procedureService->getAccessibleProcedureIds($currentUser->getUser(), $procedureId);

            if ($permissions->hasPermission('feature_statement_copy_to_foreign_procedure')) {
                $data['inaccessibleProcedures'] = $procedureService->getInaccessibleProcedureIds($currentUser->getUser());
            }
        }

        return $this->renderJson($data);
    }

    /**
     * @param array|mixed[] $submitter
     */
    protected function createCompareStringForSubmitter(array $submitter): string
    {
        // There is probably a better way to do this, any volunteers or tips?
        foreach ($submitter as $key => $field) {
            if ('' === $field) {
                // this sets empty fields to 'A' to sort them at the beginning
                $submitter[$key] = 'A';
            }
        }

        // sort priority: organisation, name, postal code, city
        $cmpStringA = $submitter['organisation'];
        $cmpStringA .= $submitter['name'];
        $cmpStringA .= $submitter['postalCode'];
        $cmpStringA .= $submitter['city'];

        return $cmpStringA;
    }

    /**
     * Save Filter params from Request to keep Filters between page loads e.g pager.
     */
    protected function rememberFilters(Request $request)
    {
        $requestPost = $request->request->all();
        // reset Filters if new Filters are set (POST) or reset Form Button is clicked
        if ($request->isMethod('POST') || $request->query->has('resetForm')) {
            $requestKeepPost = $requestPost;
            // save filters in session to keep filters while using pager
            $request->getSession()->set(
                'fragmentListKeepPost',
                $requestKeepPost
            );
        }

        return $requestPost;
    }

    /**
     * @throws Exception
     */
    protected function getInvitedSubmitter(string $procedureId, ServiceOutput $serviceOutput): array
    {
        $memberlist = $serviceOutput->procedureMemberListHandler($procedureId, []);
        $orgas = $memberlist['orgas'];

        $invitedSubmitters = new ArrayCollection();

        /** @var Orga $orga */
        foreach ($orgas as $orga) {
            /** @var Department $department */
            foreach ($orga->getDepartments() as $department) {
                $submitter = new SubmitterValueObject();
                $submitter->setEntityId($department->getId());
                $submitter->setList(SubmitterValueObject::LIST_INSTITUTION);
                $submitter->setSubmitter($orga->getName(), $department->getName(), '', $orga->getPostalcode(), $orga->getCity());
                // for now there is no need to implement setLastStatementCountyIds &&
                // setLastStatementMunicipalityIds, as permissions are not used together
                // when this changes, setting of both values needs to be implemented
                $submitter->lock();
                $invitedSubmitters->add($submitter);
            }
        }

        return $invitedSubmitters->toArray();
    }

    /**
     * @return SubmitterValueObject[]
     */
    protected function getStatementsCitizenSubmitter(string $procedureId, StatementService $statementService): array
    {
        $statements = $statementService->getCititzenStatementsByProcedureId($procedureId);

        return $this->getSubmittersFromStatements($statements, SubmitterValueObject::LIST_CITIZEN);
    }

    /**
     * @return SubmitterValueObject[]
     */
    protected function getStatementsInstitutionSubmitter(string $procedureId, StatementService $statementService): array
    {
        $statements = $statementService->getInstitutionStatementsByProcedureId($procedureId);

        return $this->getSubmittersFromStatements($statements, SubmitterValueObject::LIST_INSTITUTION);
    }

    /**
     * Build a list of submitters based on given statements.
     *
     * @param Statement[] $statements
     *
     * @return SubmitterValueObject[]
     */
    protected function getSubmittersFromStatements(array $statements, string $listType): array
    {
        $submitters = [];

        /** @var Statement $statement */
        foreach ($statements as $statement) {
            $meta = $statement->getMeta();
            $orgaName = $meta->getOrgaName();
            $departmentName = $meta->getOrgaDepartmentName();
            $authorName = $meta->getAuthorName();
            $postalCode = $meta->getOrgaPostalCode();
            $city = $meta->getOrgaCity();
            $countyIds = collect($statement->getCounties())
                ->transform(static fn (County $county) => $county->getId())->unique()->toArray();
            $municipalityIds = collect($statement->getMunicipalities())
                ->transform(static fn (Municipality $municipality) => $municipality->getId())->unique()->toArray();

            if (null !== $statement->getOrganisation()
                && !$statement->getOrganisation()->isDefaultCitizenOrganisation()) {
                $orga = $statement->getOrganisation();
                if ('' !== $orga->getPostalcode()) {
                    $postalCode = $orga->getPostalcode();
                }
                if ('' !== $orga->getCity()) {
                    $city = $orga->getCity();
                }
            }

            $submitter = new SubmitterValueObject();
            $submitter->setEntityId($statement->getId());
            $submitter->setList($listType);
            $submitter->setSubmitter($orgaName, $departmentName, $authorName, $postalCode, $city);
            $submitter->setLastStatementCountyIds($countyIds);
            $submitter->setLastStatementMunicipalityIds($municipalityIds);
            $submitter->lock();

            if (!$this->isSubmitterInArray($submitter, $submitters)) {
                $submitters[] = $submitter;
            }
        }

        return $submitters;
    }

    /**
     * We can't use ArrayCollection->contains($submitter), because the entityId
     * is changing even if the submitter information are the same. That's why.
     *
     * @param SubmitterValueObject[] $submitters
     */
    protected function isSubmitterInArray(SubmitterValueObject $submitter, array $submitters): bool
    {
        foreach ($submitters as $item) {
            if ($submitter->getSubmitter() == $item->getSubmitter()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    protected function generateNewStatementTemplateVars(
        string $procedureId,
        ServiceOutput $serviceOutput,
        StatementHandler $statementHandler,
        StatementService $statementService
    ): array {
        $templateVars = $statementHandler->generateTemplateVarsForNewStatementForm($procedureId);

        $currentRoute = $this->generateUrl(
            'DemosPlan_statement_new_submitted',
            ['procedureId' => $procedureId]
        );
        $templateVars['formAction'] = $currentRoute;
        $templateVars['abortPath'] = $currentRoute;

        if ($this->permissions->hasPermission('field_statement_user_state')) {
            $templateVars['formOptions']['userState'] = $this->getFormParameter('statement_user_state');
        }

        if ($this->permissions->hasPermission('field_statement_user_group')) {
            $templateVars['formOptions']['userGroup'] = $this->getFormParameter('statement_user_group');
        }

        if ($this->permissions->hasPermission('field_statement_user_position')) {
            $templateVars['formOptions']['userPosition'] = $this->getFormParameter('statement_user_position');
        }

        if ($this->permissions->hasPermission('feature_statement_cluster')) {
            $templateVars['table']['procedure']['clusterStatements'] = $statementHandler->getClustersOfProcedure(
                $procedureId
            );
        }

        /** Fill templateVars for submitter autofill
         *  Handle permissions:
         *      - feature_statement_create_autofill_submitter_invited
         *      - feature_statement_create_autofill_submitter_institutions
         *      - feature_statement_create_autofill_submitter_citizens.
         */
        $submitters = [];

        if ($this->permissions->hasPermission('feature_statement_create_autofill_submitter_invited')) {
            $submitters = $this->getInvitedSubmitter($procedureId, $serviceOutput);
        }

        if ($this->permissions->hasPermission('feature_statement_create_autofill_submitter_institutions')) {
            $submitters = array_merge($submitters, $this->getStatementsInstitutionSubmitter($procedureId, $statementService));
        }

        if ($this->permissions->hasPermission('feature_statement_create_autofill_submitter_citizens')) {
            $submitters = array_merge($submitters, $this->getStatementsCitizenSubmitter($procedureId, $statementService));
        }

        // Sorting submitters
        usort(
            $submitters,
            function (SubmitterValueObject $a, SubmitterValueObject $b) {
                $cmpStringA = $this->createCompareStringForSubmitter($a->getSubmitter());
                $cmpStringB = $this->createCompareStringForSubmitter($b->getSubmitter());

                return strcmp($cmpStringA, $cmpStringB);
            }
        );

        $templateVars['submitters'] = Json::encode($submitters);

        return $templateVars;
    }
}
