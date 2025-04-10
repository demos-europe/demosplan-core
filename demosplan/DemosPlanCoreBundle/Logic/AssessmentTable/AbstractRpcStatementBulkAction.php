<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\PathException;
use EDT\Wrapping\Contracts\AccessException;
use Exception;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractRpcStatementBulkAction implements RpcMethodSolverInterface
{
    /**
     * @var AssessmentTableServiceOutput
     */
    protected $assessmentTableServiceOutput;

    /**
     * @var ConditionFactoryInterface
     */
    protected $conditionFactory;

    /**
     * @var CurrentUserInterface
     */
    protected $currentUser;

    /**
     * @var JsonSchemaValidator
     */
    protected $jsonValidator;

    /**
     * @var ProcedureResourceType
     */
    protected $procedureResourceType;

    /**
     * @var ProcedureService
     */
    protected $procedureService;

    /**
     * @var RpcErrorGenerator
     */
    protected $errorGenerator;

    /**
     * @var StatementResourceType
     */
    protected $statementResourceType;

    /**
     * @var StatementService
     */
    protected $statementService;

    /**
     * @var StatementCopier
     */
    protected $statementCopier;

    public function __construct(
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        DqlConditionFactory $conditionFactory,
        CurrentUserInterface $currentUser,
        JsonSchemaValidator $jsonValidator,
        ProcedureResourceType $procedureResourceType,
        ProcedureService $procedureService,
        RpcErrorGenerator $errorGenerator,
        StatementResourceType $statementResourceType,
        StatementService $statementService,
        StatementCopier $statementCopier,
        private readonly TransactionService $transactionService,
        protected readonly StatementDeleter $statementDeleter,
        protected readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->assessmentTableServiceOutput = $assessmentTableServiceOutput;
        $this->conditionFactory = $conditionFactory;
        $this->currentUser = $currentUser;
        $this->jsonValidator = $jsonValidator;
        $this->procedureResourceType = $procedureResourceType;
        $this->procedureService = $procedureService;
        $this->errorGenerator = $errorGenerator;
        $this->statementResourceType = $statementResourceType;
        $this->statementService = $statementService;
        $this->statementCopier = $statementCopier;
    }

    abstract protected function checkIfAuthorized(string $procedureId): bool;

    abstract protected function getJsonSchemaPath(): string;

    abstract protected function handleStatementAction(array $statements): bool;

    abstract public function supports(string $method): bool;

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
            fn (): array => $this->prepareAction($procedure->getId(), $rpcRequests));
    }

    public function isTransactional(): bool
    {
        return false;
    }

    /**
     * @throws InvalidSchemaException
     * @throws JsonException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        $this->validateRpcRequestJson($rpcRequest);
    }

    private function generateMethodResult(object $rpcRequest): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = 'ok';
        $result->id = $rpcRequest->id;

        return $result;
    }

    /**
     * @throws AccessException
     * @throws PathException
     */
    private function loadRequestedStatements(array $statementIds, string $procedureId): array
    {
        $idCondition = [] === $statementIds
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasAnyOfValues($statementIds, $this->statementResourceType->id);
        $procedureCondition = $this->conditionFactory->propertyHasValue(
            $procedureId,
            $this->statementResourceType->procedure->id
        );

        return $this->statementResourceType->getEntities([$idCondition, $procedureCondition], []);
    }

    /**
     * @throws InvalidSchemaException
     * @throws JsonException
     */
    private function validateRpcRequestJson(object $rpcRequest): void
    {
        $this->jsonValidator->validate(
            Json::encode($rpcRequest),
            $this->getJsonSchemaPath()
        );
    }

    /**
     * @param array<mixed>|object $rpcRequests
     */
    private function prepareAction(string $procedureId, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        if (!$this->checkIfAuthorized($procedureId)) {
            return array_map($this->errorGenerator->accessDenied(...), $rpcRequests);
        }

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $statementIds = $rpcRequest->params->statementIds;
                $statementEntities = $this->loadRequestedStatements($statementIds, $procedureId);

                if (count($statementEntities) !== (is_countable($statementIds) ? count($statementIds) : 0)) {
                    $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);

                    return $resultResponse;
                }

                if (false === $this->handleStatementAction($statementEntities)) {
                    $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);

                    return $resultResponse;
                }

                $resultResponse[] = $this->generateMethodResult($rpcRequest);
            } catch (AccessException) {
                $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
            } catch (InvalidSchemaException|JsonException|PathException) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (Exception) {
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }
}
