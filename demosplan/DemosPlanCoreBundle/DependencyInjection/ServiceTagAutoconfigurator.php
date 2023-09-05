<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DependencyInjection;

use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory\DataGeneratorInterface;
use demosplan\DemosPlanCoreBundle\Logic\Deployment\StrategyInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A place to store all service container tags to be registered for autoconfiguration.
 */
final class ServiceTagAutoconfigurator
{
    /**
     * Strategies for demosplan deployment.
     *
     * @const string
     */
    public const DEPLOYMENT_STRATEGIES = 'dplan.deployment.strategy';

    /**
     * Method solvers for the generic RPC implementation.
     *
     * @const string
     */
    public const RPC_METHOD_SOLVERS = 'dplan.rpc.method.solver';

    /**
     * Generators for faked contents of several file formats.
     *
     * @const string
     */
    private const FAKE_DATA_GENERATOR = 'dplan.data.generator';

    private const CLASS_MAP = [
        self::DEPLOYMENT_STRATEGIES => StrategyInterface::class,
        self::FAKE_DATA_GENERATOR   => DataGeneratorInterface::class,
        self::RPC_METHOD_SOLVERS    => RpcMethodSolverInterface::class,
    ];

    public static function configure(ContainerBuilder $containerBuilder): void
    {
        foreach (self::CLASS_MAP as $tag => $interface) {
            $containerBuilder->registerForAutoconfiguration($interface)
                ->addTag($tag);
        }
    }
}
