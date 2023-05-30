<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\CoreApplication\Application\DemosPlanTestKernel;

/**
 * This makes sure we initialize the kernel with the currently to-be-tested project.
 */
trait MonoKernelTrait
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        if (null === static::$class) {
            static::$class = static::getKernelClass();
        }

        if (isset($options['environment'])) {
            $env = $options['environment'];
        } elseif (isset($_ENV['APP_ENV'])) {
            $env = $_ENV['APP_ENV'];
        } elseif (isset($_SERVER['APP_ENV'])) {
            $env = $_SERVER['APP_ENV'];
        } else {
            $env = 'test';
        }

        if (isset($options['debug'])) {
            $debug = $options['debug'];
        } elseif (isset($_ENV['APP_DEBUG'])) {
            $debug = $_ENV['APP_DEBUG'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $debug = $_SERVER['APP_DEBUG'];
        } else {
            $debug = true;
        }

        return new static::$class(static::getActiveProject(), $env, $debug);
    }

    protected static function getActiveProject(): string
    {
        return DemosPlanTestKernel::TEST_PROJECT_NAME;
    }

    protected static function getKernelClass(): string
    {
        if (DemosPlanTestKernel::TEST_PROJECT_NAME === static::getActiveProject()) {
            return DemosPlanTestKernel::class;
        }

        return DemosPlanKernel::class;
    }
}
