<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler;

use demosplan\DemosPlanCoreBundle\DependencyInjection\ServiceTagAutoconfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverStrategy;

class RpcMethodSolverPass extends ServiceTagFactoryPass
{
    protected function getFactoryArgument(): string
    {
        return '$rpcMethodSolvers';
    }

    protected function getFactoryClass(): string
    {
        return RpcMethodSolverStrategy::class;
    }

    protected function getTagName(): string
    {
        return ServiceTagAutoconfigurator::RPC_METHOD_SOLVERS;
    }
}
