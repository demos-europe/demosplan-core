<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Rpc;

use JsonSchema\Exception\InvalidSchemaException;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;

/**
 * Interface to be implemented by the classes that execute/solve RPC Methods.
 */
interface RpcMethodSolverInterface
{
    public function supports(string $method): bool;

    /**
     * @param array|object $rpcRequests
     *
     * @return array<int|string,mixed>
     */
    public function execute(?Procedure $procedure, $rpcRequests): array;

    /**
     * Returns true if given a request with an array of rpc methods supported by the same
     * solver, they must be executed in a transactional mode.
     */
    public function isTransactional(): bool;

    /**
     * @throws InvalidSchemaException
     * @throws AccessDeniedException
     */
    public function validateRpcRequest(object $rpcRequest): void;
}

