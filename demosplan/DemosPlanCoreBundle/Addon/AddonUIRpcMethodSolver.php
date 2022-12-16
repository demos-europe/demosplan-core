<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;

class AddonUIRpcMethodSolver implements RpcMethodSolverInterface
{
    public function supports(string $method): bool
    {
        return 'demosplan.addon.ui.hook' === $method;
    }

    public function execute(?Procedure $procedure, $rpcRequests): array
    {
        foreach ($rpcRequests as $rpcRequest) {
            // TODO: implement sending addon bundle source to the frontend. stuff. is. there.
        }
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        // TODO: Implement validateRpcRequest() method.
    }
}
