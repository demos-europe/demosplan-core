<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Workflow;

use Symfony\Component\Stopwatch\Stopwatch;

class ProfilerService
{
    protected Stopwatch $stopwatch;
    public const ELASTICSEARCH_PROFILER = 'ES';
    public const RABBITPDF_PROFILER = 'Rabbit PDF';
    public const REQUESTGEODB_PROFILER = 'Request GeoDB';
    public const CONVERTESHITS_PROFILER = 'ConvertESHits';

    public function __construct()
    {
        $this->stopwatch = new Stopwatch(true);
    }

    public function profilerStart(string $name)
    {
        $this->stopwatch->start($name);
    }

    public function profilerStop(string $name)
    {
        $this->stopwatch->stop($name);
    }
}
