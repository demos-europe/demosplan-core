<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use LogicException;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\CoreApplication\Application\DemosPlanTestKernel;

trait PluginTestTrait
{
    protected static function getEnabledPlugins(): array
    {
        throw new LogicException('A plugin test must define the required plugins');
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var DemosPlanTestKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->setPlugins(self::getEnabledPlugins());

        return $kernel;
    }
}
