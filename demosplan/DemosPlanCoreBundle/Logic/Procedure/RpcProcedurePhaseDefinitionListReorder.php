<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Logic\ReorderEntityListByInteger;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedurePhaseDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
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
 *   "phaseDefinitionId": <JSON string>,
 *   "newIndex": <JSON integer>
 * }
 * ```.
 *
 * `phaseDefinitionId`: Represents a {@link ProcedurePhaseDefinition} by its ID. Its
 * orderInAudience is the property that gets changed.
 * `newIndex`: The position (0-based, among the non-configuration phases of the same
 * audience) where the phase definition should be put at.
 *
 * The configuration phase (orderInAudience 0) is always excluded from the reorderable
 * scope: it may neither be moved, nor be a valid target index.
 */
class RpcProcedurePhaseDefinitionListReorder implements RpcMethodSolverInterface
{
    final public const JSON_SCHEMA_PATH = 'json-schema/rpc-procedurePhaseDefinition-list-reorder-schema.json';
    final public const SUPPORTED_METHOD_NAME = 'procedurePhaseDefinitionList.reorder';

    public function __construct(
        private readonly DqlConditionFactory $conditionFactory,
        private readonly RpcErrorGenerator $errorGenerator,
        private readonly JsonSchemaValidator $jsonValidator,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedurePhaseDefinitionResourceType $procedurePhaseDefinitionResourceType,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly TransactionService $transactionService,
    ) {
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
            fn (): array => $this->prepareAndExecuteAction($rpcRequests));
    }

    public function isTransactional(): bool
    {
        return true;
    }

    /**
     * @throws InvalidSchemaException
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
    private function prepareAndExecuteAction($rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        if (!$this->permissions->hasPermission('area_customer_procedure_phase_definitions')) {
            return array_map($this->errorGenerator->accessDenied(...), $rpcRequests);
        }

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $phaseDefinitionId = $rpcRequest->params->phaseDefinitionId;
                $newIndex = $rpcRequest->params->newIndex;

                $movedPhase = $this->loadPhaseDefinition($phaseDefinitionId);
                if (null === $movedPhase || $movedPhase->isConfigurationPhase()) {
                    $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
                    continue;
                }

                $phasesOfAudience = $this->loadPhaseDefinitionsForAudience($movedPhase->getAudience());
                $listReorder = new ReorderEntityListByInteger($newIndex, $phaseDefinitionId, $phasesOfAudience);
                $listReorder->reorderEntityList();
            } catch (Exception) {
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);

                return $resultResponse;
            }
        }

        return $resultResponse;
    }

    /**
     * @throws PathException
     */
    private function loadPhaseDefinition(string $id): ?ProcedurePhaseDefinition
    {
        $idCondition = $this->conditionFactory->propertyHasValue($id, $this->procedurePhaseDefinitionResourceType->id);
        $phases = $this->procedurePhaseDefinitionResourceType->getEntities([$idCondition], []);

        return $phases[0] ?? null;
    }

    /**
     * @return Collection<int, ProcedurePhaseDefinition> keys are the orderInAudience of the corresponding phase
     *
     * @throws PathException
     */
    private function loadPhaseDefinitionsForAudience(string $audience): Collection
    {
        $audienceCondition = $this->conditionFactory->propertyHasValue($audience, $this->procedurePhaseDefinitionResourceType->audience);
        $orderCondition = $this->conditionFactory->valueGreaterThan(0, $this->procedurePhaseDefinitionResourceType->orderInAudience);
        $sortMethod = $this->sortMethodFactory->propertyAscending(['orderInAudience']);

        $phases = $this->procedurePhaseDefinitionResourceType->getEntities([$audienceCondition, $orderCondition], [$sortMethod]);

        $result = new ArrayCollection();
        foreach ($phases as $phase) {
            $result->set($phase->getOrderInAudience(), $phase);
        }

        return $result;
    }
}
