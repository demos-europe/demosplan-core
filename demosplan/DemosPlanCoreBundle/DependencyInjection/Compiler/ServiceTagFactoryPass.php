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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A base class to simplify registering tagged services with factory classes.
 */
abstract class ServiceTagFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->getFactoryClass())) {
            return;
        }

        $factoryServices = [];

        foreach ($container->findTaggedServiceIds($this->getTagName()) as $key => $value) {
            $factoryServices[] = new Reference($key);
        }

        $container
            ->getDefinition($this->getFactoryClass())
            ->setArgument($this->getFactoryArgument(), $factoryServices);
    }

    abstract protected function getFactoryArgument(): string;

    abstract protected function getFactoryClass(): string;

    abstract protected function getTagName(): string;
}
