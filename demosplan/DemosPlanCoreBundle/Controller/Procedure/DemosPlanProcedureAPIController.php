<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Logger\ApiLoggerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceLinkageFactory;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PublicIndexProcedureLister;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\UserFilterSetService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFilterHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\HashedQueryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureResourceType;
use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use demosplan\DemosPlanCoreBundle\Transformers\Procedure\AssessmentTableFilterTransformer;
use demosplan\DemosPlanCoreBundle\Transformers\Procedure\ProcedureArrayTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\AssessmentTableFilter;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\Validation\FieldsValidator;
use EDT\PathBuilding\PathBuildException;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanProcedureAPIController extends APIController
{
    public function __construct(
        ApiLoggerInterface $apiLogger,
        private readonly ProcedureHandler $procedureHandler,
        FieldsValidator $fieldsValidator,
        PrefilledTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        GlobalConfigInterface $globalConfig,
        MessageBagInterface $messageBag,
        MessageFormatter $messageFormatter,
        SchemaPathProcessor $schemaPathProcessor,
    ) {
        parent::__construct(
            $apiLogger,
            $resourceTypeProvider,
            $fieldsValidator,
            $translator,
            $logger,
            $globalConfig,
            $messageBag,
            $schemaPathProcessor,
            $messageFormatter
        );
    }

    /**
     * @DplanPermissions("area_public_participation")
     */
    #[Route(path: '/api/1.0/procedure/', methods: ['GET'], name: 'dplan_api_procedure_')]
    public function listAction(Request $request): APIResponse
    {
        $rawData = $this->forward(
            'demosplan\DemosPlanCoreBundle\Controller\Procedure\DemosPlanProcedureAPIController::searchProceduresAjaxAction',
            $request->query->all()
        );
        $data = Json::decodeToArray($rawData->getContent());

        return $this->renderCollection($data['data'], ProcedureArrayTransformer::class);
    }

    /**
     * @DplanPermissions("feature_procedures_mark_participated")
     *
     * @param string $procedureId
     *
     * @return JsonResponse
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/mark/participated', methods: ['POST'], name: 'dp_api_procedure_mark_participated', options: ['expose' => true])]
    public function markParticipatedAction($procedureId)
    {
        try {
            $this->procedureHandler->markParticipated($procedureId);

            return $this->createResponse([], 200);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.procedure.markParticipated');

            return $this->handleApiError($e);
        }
    }

    /**
     * @DplanPermissions("feature_procedures_mark_participated")
     *
     * @param string $procedureId
     *
     * @return JsonResponse
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/unmark/participated', methods: ['POST'], name: 'dp_api_procedure_unmark_participated', options: ['expose' => true])]
    public function unMarkParticipatedAction($procedureId)
    {
        try {
            $this->procedureHandler->unmarkParticipated($procedureId);

            return $this->createResponse([], 200);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.procedure.markParticipated');

            return $this->handleApiError($e);
        }
    }

    /**
     * Returns a JSON with the available filters for the assessment table.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @return APIResponse
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/statementemptyfilters', methods: ['GET'], name: 'dp_api_procedure_get_statement_empty_filters', options: ['expose' => true])]
    #[Route(path: '/api/1.0/procedures/{procedureId}/statementemptyfilters', methods: ['GET'], name: 'dp_api_procedure_get_statement_empty_filters', options: ['expose' => true])]
    public function getStatementEmptyFilterAction(StatementFilterHandler $statementFilterHandler)
    {
        return $this->getStatementEmptyFilter($statementFilterHandler);
    }

    /**
     * Returns a JSON with the available filters for the original statements list.
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @return APIResponse
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/originalstatementemptyfilters', methods: ['GET'], name: 'dp_api_procedure_get_original_statement_empty_filters', options: ['expose' => true])]
    public function getOriginalStatementEmptyFilterAction(StatementFilterHandler $statementFilterHandler)
    {
        return $this->getStatementEmptyFilter($statementFilterHandler, true);
    }

    /**
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param string $procedureId
     * @param string $filterHash
     *
     * @return APIResponse
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/originalfilters/{filterHash}', methods: ['GET'], name: 'dp_api_procedure_get_original_filters', options: ['expose' => true])]
    public function getOriginalStatementFilterAction(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        HashedQueryService $filterSetService,
        PermissionsInterface $permissions,
        Request $request,
        StatementFilterHandler $statementFilterHandler,
        $procedureId,
        $filterHash = '',
    ) {
        return $this->getStatementFilter(
            $assessmentHandler,
            $assessmentTableServiceOutput,
            $filterSetService,
            $permissions,
            $request,
            $statementFilterHandler,
            $procedureId,
            $filterHash,
            true
        );
    }

    /**
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param string $procedureId
     * @param string $filterHash
     *
     * @return APIResponse
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/statementfilters/{filterHash}', methods: ['GET'], name: 'dp_api_procedure_get_statement_filters', options: ['expose' => true])]
    public function getNonOriginalStatementFilterAction(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        HashedQueryService $filterSetService,
        PermissionsInterface $permissions,
        Request $request,
        StatementFilterHandler $statementFilterHandler,
        $procedureId,
        $filterHash = '',
    ) {
        return $this->getStatementFilter(
            $assessmentHandler,
            $assessmentTableServiceOutput,
            $filterSetService,
            $permissions,
            $request,
            $statementFilterHandler,
            $procedureId,
            $filterHash,
            false
        );
    }

    /**
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param string $procedureId
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/updatefilterhash', methods: ['POST'], name: 'dplan_api_procedure_update_filter_hash', options: ['expose' => true])]
    public function updateNonOriginalFilterSetAction(
        AssessmentHandler $assessmentHandler,
        Request $request,
        $procedureId,
    ): APIResponse {
        return $this->updateFilterSet($assessmentHandler, $request, $procedureId, false);
    }

    /**
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param string $procedureId
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/updatefilterhash/original', methods: ['POST'], name: 'dplan_api_procedure_update_original_filter_hash', options: ['expose' => true])]
    public function updateOriginalFilterSetAction(
        AssessmentHandler $assessmentHandler,
        Request $request,
        $procedureId,
    ): APIResponse {
        return $this->updateFilterSet($assessmentHandler, $request, $procedureId, true);
    }

    /**
     * @return APIResponse
     */
    private function getStatementEmptyFilter(StatementFilterHandler $statementFilterHandler, bool $original = false)
    {
        $availableFilters = $statementFilterHandler->getAvailableFilters($original);

        $responseData = [];
        foreach ($availableFilters as $filter) {
            // user has no permission to use this filter
            if (!$filter['hasPermission']) {
                continue;
            }
            $filterKey = $filter['key'];
            $filterName = 'filter_'.$filterKey;
            $filterLabel = $statementFilterHandler->getTranslatedFilterLabel($filterKey);

            $assessmentTableFilter = new AssessmentTableFilter();
            $assessmentTableFilter->setName($filterName);
            $assessmentTableFilter->setLabel($filterLabel);
            $assessmentTableFilter->setType($filter['type']);
            $assessmentTableFilter->setAvailableOptions([]);
            $assessmentTableFilter->setSelectedOptions([]);
            $assessmentTableFilter->lock();
            $responseData[] = $assessmentTableFilter;
        }

        return $this->renderCollection($responseData, AssessmentTableFilterTransformer::class);
    }

    /**
     * @param string      $procedureId
     * @param string|null $filterHash
     * @param bool        $original
     *
     * @return APIResponse
     *
     * - initial pageload needs hash to restore old filterSet
     * - when user interacts with the vue filter modal we get no hash
     * But we can implement a lookup later
     *
     * Gets an array of filters used in assessmenttable
     */
    private function getStatementFilter(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        HashedQueryService $filterSetService,
        PermissionsInterface $permissions,
        Request $request,
        StatementFilterHandler $statementFilterHandler,
        $procedureId,
        $filterHash,
        $original,
    ) {
        // @improve T14122
        $filterSet = $filterSetService->findHashedQueryWithHash($filterHash);
        if (null === $filterSet) {
            $filterSet = $assessmentHandler->handleFilterHash($request, $procedureId, $filterHash, $original);
        }

        $rParams = [];

        /** @var AssessmentTableQuery $filterHashValueObject */
        $filterHashValueObject = $filterSet->getStoredQuery();
        $rParams['filters'] = $filterHashValueObject->getFilters();

        // NOTE (SG): I reverted this to an unsafe check because I'm not sure about
        //            the value of search word in already stored filter sets.
        if ('' !== $filterHashValueObject->getSearchWord()) {
            $rParams['search'] = $filterHashValueObject->getSearchWord();
        }

        if ((!$permissions->hasPermission('feature_original_statements_use_pager') && true === $original)
            || (!$permissions->hasPermission('feature_assessmenttable_use_pager') && false === $original)) {
            // hotfix #11850 display all original SN in list
            $rParams['request']['limit'] = 1_000_000;
        }

        $res = $assessmentTableServiceOutput->getStatementListHandler(
            $procedureId,
            $rParams,
            true,
            0,
            false
        );

        $responseData = [];
        $esFilters = $res->getFilterSet()['filters'];

        $requestCollection = collect($rParams['filters']);
        $availableFilters = $statementFilterHandler->getAvailableFilters($original);

        foreach ($availableFilters as $filter) {
            $filterKey = $filter['key'];
            // could not find filter at all
            if (!array_key_exists($filterKey, $esFilters)) {
                continue;
            }
            // user has no permission to use this filter
            if (!$filter['hasPermission']) {
                continue;
            }
            $filterName = 'filter_'.$filterKey;
            $label = $statementFilterHandler->getTranslatedFilterLabel($filterKey);
            $options = $statementFilterHandler->getTranslatedFilterOptions($filterKey, $esFilters[$filterKey], $filter['type']);
            $selectedOptions = [];

            // find selected options
            if ($requestCollection->has($filterKey)) {
                // take all options of a select
                $selectedOptions = collect($options)
                    ->filter(static function ($filterOption) use ($requestCollection, $filterKey) {
                        // check, whether value of current option is in request
                        // "normalize" value null (from ES) to "" (from Filter)
                        $value = $filterOption['value'] ?? '';
                        $selectedFilterValues = $requestCollection->get($filterKey);
                        $filterOptionIsSelected = false;
                        if (in_array($value, $selectedFilterValues, true)) {
                            $filterOptionIsSelected = true;
                        }

                        return $filterOptionIsSelected;
                    })->values()->all();
            }
            $selected = $statementFilterHandler->getTranslatedFilterOptions($filter['key'], $selectedOptions, $filter['type']);

            $assessmentTableFilter = new AssessmentTableFilter();
            $assessmentTableFilter->setName($filterName);
            $assessmentTableFilter->setLabel($label);
            $assessmentTableFilter->setType($filter['type']);
            $assessmentTableFilter->setAvailableOptions($options);
            $assessmentTableFilter->setSelectedOptions($selected);
            $assessmentTableFilter->lock();
            $responseData[] = $assessmentTableFilter;
        }

        return $this->renderCollection($responseData, AssessmentTableFilterTransformer::class);
    }

    /**
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @param string $filterSetId
     *
     * @return JsonResponse
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/procedures/{procedureId}/statementfilters/delete/{filterSetId}', methods: ['DELETE'], name: 'dplan_api_procedure_delete_statement_filter', options: ['expose' => true])]
    public function deleteStatementFilterAction(Request $request, UserFilterSetService $userFilterSetService, $filterSetId)
    {
        try {
            // @improve T14122
            $successful = $userFilterSetService->deleteUserFilterSet($filterSetId);

            if ($successful) {
                $this->messageBag->add('confirm', 'confirm.savedFilterSet.deleted');

                return $this->renderDelete();
            }

            $this->messageBag->add('error', 'error.savedFilterSet.deleted');

            return $this->renderDelete(Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.savedFilterSet.deleted');

            return $this->handleApiError($e);
        }
    }

    /**
     * @param string $procedureId
     * @param bool   $original
     */
    private function updateFilterSet(AssessmentHandler $assessmentHandler, Request $request, $procedureId, $original): APIResponse
    {
        // turn the funny query into something usable
        // this is jquery based legacy and a very hacky result of T9159
        // the frontend sends each form value as json encoded { "name", "value" }
        $query = Json::decodeToArray($request->getContent());

        foreach ($query as $item) {
            $requestData = $item;
            $multiselect = false;
            // check if multiselect
            if (str_contains((string) $requestData['name'], '[]')) {
                $multiselect = true;
                $requestData['name'] = str_replace('[]', '', (string) $requestData['name']);
            }
            if ($multiselect) {
                $filter = $request->request->all($requestData['name']);
                $filter[] = $requestData['value'];
                $request->request->set($requestData['name'], $filter);
            } else {
                $request->request->set($requestData['name'], $requestData['value']);
            }
        }

        // handle the filter hash
        $filterSet = $assessmentHandler->handleFilterHash($request, $procedureId, null, $original);
        $resource = $this->resourceService->makeItemOfResource($filterSet, HashedQueryResourceType::getName());

        // return the filter hash
        return $this->renderResource($resource);
    }

    /**
     * @DplanPermissions("area_admin_invitable_institution")
     */
    #[Route(path: '/api/1.0/procedure/{procedureId}/relationships/invitedPublicAffairsAgents', methods: ['POST'], name: 'dplan_api_procedure_add_invited_public_affairs_bodies', options: ['expose' => true])]
    public function addInvitedPublicAffairsAgentsAction(
        Request $request,
        ResourceLinkageFactory $linkageFactory,
        string $procedureId,
    ): JsonResponse {
        // Check if normalizer succeeded, even if we don't need its object here
        if (null === $this->requestData) {
            throw BadRequestException::normalizerFailed();
        }

        $resourceLinkage = $linkageFactory->createFromJsonRequestString(
            $request->getContent()
        );

        $this->procedureHandler->addInvitedPublicAffairsAgents($procedureId, $resourceLinkage);

        return $this->renderEmpty(Response::HTTP_NO_CONTENT);
    }

    /**
     * Search for Procedures and returns a resultlist
     * in json format.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_search_ajax', path: '/verfahren/suche/ajax', options: ['expose' => true])]
    public function searchProceduresAjaxAction(
        ProcedureResourceType $procedureResourceType,
        PublicIndexProcedureLister $procedureLister,
        ProcedureService $procedureService,
        Request $request,
    ) {
        $this->fractal->parseFieldsets([
            $procedureResourceType::getName() => $this->buildSparseFieldsetString(
                $procedureResourceType->coordinate,
                $procedureResourceType->id,
                $procedureResourceType->externalDescription,
                $procedureResourceType->owningOrganisationName,
                $procedureResourceType->statementSubmitted,
                $procedureResourceType->externalName,
                $procedureResourceType->externalStartDate,
                $procedureResourceType->externalEndDate,
                $procedureResourceType->externalPhaseTranslationKey,
                $procedureResourceType->name,
                $procedureResourceType->internalStartDate,
                $procedureResourceType->internalEndDate,
                $procedureResourceType->internalPhaseTranslationKey,
                $procedureResourceType->daysLeft,
                $procedureResourceType->internalPhasePermissionset,
                $procedureResourceType->externalPhasePermissionset
            ),
        ]);

        $orgaSlug = $request->query->get('orgaSlug', '');
        $procedureList = $procedureLister->getPublicIndexProcedureList($request, $orgaSlug);
        $procedureIds = array_column($procedureList['list']['procedurelist'], 'id', 'id');
        $procedures = $procedureService->getProceduresById($procedureIds);

        // use the order of the original list
        foreach ($procedures as $procedure) {
            $procedureIds[$procedure->getId()] = $procedure;
        }

        $collection = $this->resourceService->makeCollectionOfResources($procedureIds, $procedureResourceType::getName());

        return $this->renderResource($collection);
    }

    /**
     * @throws PathBuildException
     */
    private function buildSparseFieldsetString(PropertyPathInterface $path, PropertyPathInterface ...$paths): string
    {
        array_unshift($paths, $path);

        return implode(
            ',',
            array_map(
                static fn (PropertyPathInterface $path): string => $path->getAsNamesInDotNotation(),
                $paths
            )
        );
    }
}
