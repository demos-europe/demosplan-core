<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Workflow;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\ReorderEntityListByInteger;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlaceResourceType;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Doctrine\Common\Collections\ArrayCollection;
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
     * @var EntityFetcher
     */
    protected $entityFetcher;

    /**
     * @var JsonSchemaValidator
     */
    protected $jsonValidator;

    /**
     * @var RpcErrorGenerator
     */
    protected $errorGenerator;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    /**
     * @var PlaceResourceType
     */
    private $placeResourceType;

    /**
     * @var ProcedureService
     */
    protected $procedureService;

    /**
     * @var SortMethodFactory
     */
    private $sortMethodFactory;

    /**
     * @var TransactionService
     */
    private $transactionService;

    public const JSON_SCHEMA_PATH = 'demosplan/DemosPlanCoreBundle/Resources/config/json-schema/rpc-workflowPlace-list-reorder-schema.json';
    public const SUPPORTED_METHOD_NAME = 'workflowPlacesOfProcedure.reorder';

    public function __construct(
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        JsonSchemaValidator $jsonSchemaValidator,
        PermissionsInterface $permissions,
        PlaceResourceType $placeResourceType,
        ProcedureService $procedureService,
        RpcErrorGenerator $errorGenerator,
        SortMethodFactory $sortMethodFactory,
        TransactionService $transactionService
    ) {
        $this->conditionFactory = $conditionFactory;
        $this->entityFetcher = $entityFetcher;
        $this->errorGenerator = $errorGenerator;
        $this->jsonValidator = $jsonSchemaValidator;
        $this->permissions = $permissions;
        $this->placeResourceType = $placeResourceType;
        $this->procedureService = $procedureService;
        $this->sortMethodFactory = $sortMethodFactory;
        $this->transactionService = $transactionService;
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
    public function execute(?Procedure $procedure, $rpcRequests): array
    {
        return $this->transactionService->executeAndFlushInTransaction(
            function () use ($procedure, $rpcRequests): array {
                return $this->prepareAndExecuteAction($procedure->getId(), $rpcRequests);
            });
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
            DemosPlanPath::getRootPath(self::JSON_SCHEMA_PATH)
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
            return array_map([$this->errorGenerator, 'accessDenied'], $rpcRequests);
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
            } catch (Exception $e) {
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
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, Place> keys are the sortIndex of the corresponding {@link Place} value
     *
     * @throws PathException
     */
    private function loadPlaces(string $procedureId): \Doctrine\Common\Collections\Collection
    {
        $procedureCondition = $this->conditionFactory->propertyHasValue($procedureId, $this->placeResourceType->procedure->id);
        $sortMethod = $this->sortMethodFactory->propertyAscending(['sortIndex']);

        if (!$this->placeResourceType instanceof UpdatableDqlResourceTypeInterface) {
            throw new AccessDeniedException('Entity is not Updatable');
        }

        /** @var array<int, Place> $places */
        $places = $this->entityFetcher->listEntities($this->placeResourceType, [$procedureCondition], [$sortMethod]);

        $result = new ArrayCollection();
        /** @var Place $place */
        foreach ($places as $place) {
            $result->set($place->getSortIndex(), $place);
        }

        return $result;
    }
}
