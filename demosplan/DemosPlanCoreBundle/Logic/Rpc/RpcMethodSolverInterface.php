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

use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodAddonSolverInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use JsonSchema\Exception\InvalidSchemaException;

/**
 * Interface to be implemented by the classes that execute/solve RPC Methods.
 */
interface RpcMethodSolverInterface extends RpcMethodAddonSolverInterface
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
