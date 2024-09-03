<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Workflow;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ReorderEntityListByInteger;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlaceResourceType;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\PathException;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;

/**
 * You find general RPC API usage information
 * {@link http://dplan-documentation.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}. Accepted parameters by this route are the following:
 * ```
 * "params": {
 *   "workflowPlaceId": <JSON string>,
 *   "newWorkflowPlaceIndex": <JSON integer>
 * }
 * ```.
 *
 * `workflowPlaceId`: Represents a Place Entity ("{@link Place}") by its ID. The
 * sortIndex is the property that gets changed.
 * * `newWorkflowPlaceIndex`: The position where the workflowPlace should be put at.
 */
class RpcPlaceListReorder implements RpcMethodSolverInterface
{
    /**
     * @var ConditionFactoryInterface
     */
    protected $conditionFactory;

    /**
     * @var JsonSchemaValidator
     */
    protected $jsonValidator;

    /**
     * @var RpcErrorGenerator
     */
    protected $errorGenerator;

    /**
     * @var ProcedureService
     */
    protected $procedureService;

    final public const JSON_SCHEMA_PATH = 'json-schema/rpc-workflowPlace-list-reorder-schema.json';
    final public const SUPPORTED_METHOD_NAME = 'workflowPlacesOfProcedure.reorder';

    public function __construct(
        DqlConditionFactory $conditionFactory,
        JsonSchemaValidator $jsonSchemaValidator,
        private readonly PermissionsInterface $permissions,
        private readonly PlaceResourceType $placeResourceType,
        ProcedureService $procedureService,
        RpcErrorGenerator $errorGenerator,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly TransactionService $transactionService
    ) {
        $this->conditionFactory = $conditionFactory;
        $this->errorGenerator = $errorGenerator;
        $this->jsonValidator = $jsonSchemaValidator;
        $this->procedureService = $procedureService;
    }

    public function supports(string $method): bool
    {
        return self::SUPPORTED_METHOD_NAME === $method;
    }

    /**
     * @param array<mixed>|object $rpcRequests
     *
     * @throws ConnectionException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        return $this->transactionService->executeAndFlushInTransaction(
            fn (): array => $this->prepareAndExecuteAction($procedure->getId(), $rpcRequests));
    }

    public function isTransactional(): bool
    {
        return true;
    }

    /**
     * @throws InvalidSchemaException
     * @throws JsonException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        $this->jsonValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getConfigPath(self::JSON_SCHEMA_PATH)
        );
    }

    /**
     * @param array<mixed>|object $rpcRequests
     */
    private function prepareAndExecuteAction(string $procedureId, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        if (!$this->checkIfAuthorized($procedureId)
        ) {
            return array_map($this->errorGenerator->accessDenied(...), $rpcRequests);
        }

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $workFlowPlaceId = $rpcRequest->params->workflowPlaceId;
                $newWorkFlowPlaceIndex = $rpcRequest->params->newWorkflowPlaceIndex;
                $allPlacesOfProcedure = $this->loadPlaces($procedureId);
                $listReorder = new ReorderEntityListByInteger(
                    $newWorkFlowPlaceIndex,
                    $workFlowPlaceId,
                    $allPlacesOfProcedure
                );
                $listReorder->reorderEntityList();
            } catch (Exception) {
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);

                return $resultResponse;
            }
        }

        return $resultResponse;
    }

    private function checkIfAuthorized(string $procedureId): bool
    {
        try {
            return $this->procedureService->isUserAuthorized($procedureId)
                && $this->permissions->hasPermission('area_manage_segment_places');
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @return Collection<int, Place> keys are the sortIndex of the corresponding {@link Place} value
     *
     * @throws PathException
     */
    private function loadPlaces(string $procedureId): Collection
    {
        $procedureCondition = $this->conditionFactory->propertyHasValue($procedureId, $this->placeResourceType->procedure->id);
        $sortMethod = $this->sortMethodFactory->propertyAscending(['sortIndex']);
        $places = $this->placeResourceType->getEntities([$procedureCondition], [$sortMethod]);

        $result = new ArrayCollection();
        foreach ($places as $place) {
            $result->set($place->getSortIndex(), $place);
        }

        return $result;
    }
}
