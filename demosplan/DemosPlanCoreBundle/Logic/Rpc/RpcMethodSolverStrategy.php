<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Rpc;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use Exception;
use GuzzleHttp\Exception\InvalidArgumentException;
use JsonSchema\Exception\InvalidSchemaException;
use Psr\Log\LoggerInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;

class RpcMethodSolverStrategy
{
    /**
     * @param iterable<RpcMethodSolverInterface> $rpcMethodSolvers
     */
    public function __construct(private readonly iterable $rpcMethodSolvers, private readonly CurrentProcedureService $currentProcedureService, private readonly LoggerInterface $logger, private readonly RpcErrorGenerator $errorGenerator, private readonly RpcValidator $rpcValidator)
    {
    }

    /**
     * @throws InvalidSchemaException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws Exception
     */
    public function executeMethodSolver(?Procedure $procedure, string $rpcRequestOrRequests): array
    {
        $this->rpcValidator->validateRpcJsonRequest($rpcRequestOrRequests);

        $rpcRequests = is_object(Json::decodeToMatchingType($rpcRequestOrRequests))
            ? [Json::decodeToMatchingType($rpcRequestOrRequests)]
            : Json::decodeToMatchingType($rpcRequestOrRequests);

        $resultObjects = $transactionalRpcRequests = [];
        foreach ($rpcRequests as $rpcRequest) {
            [$solverFound, $transactionalRpcRequests, $resultObjects] =
                $this->handleRpcRequest(
                    $procedure,
                    $rpcRequest,
                    $transactionalRpcRequests,
                    $resultObjects
                );
            if (!$solverFound) {
                $resultObjects[] = $this->errorGenerator->methodNotFound($rpcRequest);
            }
        }

        $resultObjects = $this->executeTransactionalSolvers(
            $procedure,
            $transactionalRpcRequests,
            $resultObjects
        );

        return $resultObjects;
    }

    private function handleRpcRequest(
        ?Procedure $procedure,
        object $rpcRequest,
        array $transactionalRpcRequests,
        array $resultObjects
    ): array {
        $solverFound = false;
        /** @var RpcMethodSolverInterface $methodSolver */
        foreach ($this->rpcMethodSolvers as $methodSolver) {
            try {
                $solverFound = $methodSolver->supports($rpcRequest->method);
                if ($solverFound) {
                    if ($methodSolver->isTransactional()) {
                        $transactionalRpcRequests = $this->storeTransactionalRpcRequests(
                            $rpcRequest,
                            $transactionalRpcRequests,
                            $methodSolver
                        );
                    } else {
                        $resultObjects = $methodSolver->execute($procedure, $rpcRequest);
                    }
                    break;
                }
            } catch (Exception $e) {
                $this->logger->error('RPC server error', ['exception' => $e]);
                $resultObjects[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return [$solverFound, $transactionalRpcRequests, $resultObjects];
    }

    private function storeTransactionalRpcRequests(
        object $rpcRequest,
        array $transactionalMethods,
        RpcMethodSolverInterface $methodSolver
    ): array {
        $solverClass = $methodSolver::class;

        if (isset($transactionalMethods[$solverClass])) {
            $transactionalMethods[$solverClass]['rpcRequests'][] = $rpcRequest;
        } else {
            $transactionalMethods[$solverClass] =
                [
                    'methodSolver' => $methodSolver,
                    'rpcRequests'  => [$rpcRequest],
                ];
        }

        return $transactionalMethods;
    }

    private function executeTransactionalSolvers(
        ?Procedure $procedure,
        array $transactionalMethods,
        array $resultObjects
    ): array {
        foreach ($transactionalMethods as $transactionalMethod) {
            /** @var RpcMethodSolverInterface $methodSolver */
            $methodSolver = $transactionalMethod['methodSolver'];
            $resultObjects = array_merge(
                $resultObjects,
                $methodSolver->execute(
                    $procedure,
                    $transactionalMethod['rpcRequests']
                )
            );
        }

        return $resultObjects;
    }
}
