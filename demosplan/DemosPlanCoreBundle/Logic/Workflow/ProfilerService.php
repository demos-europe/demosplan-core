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
use Symfony\Contracts\Service\Attribute\Required;

class ProfilerService
{
    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @return Stopwatch
     */
    public function getStopwatch()
    {
        return $this->stopwatch;
    }

    #[Required]
    public function setStopwatch(Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    /**
     * Starte den Profiler, Anzeige in der Symfony Toolbar.
     *
     * @param string $name
     */
    public function profilerStart($name)
    {
        if (null === $this->stopwatch) {
            $this->stopwatch = new Stopwatch(true);
        }

        $stopwatch = $this->getStopwatch();
        $stopwatch->start($name);
    }

    /**
     * Stoppt den Profiler.
     *
     * @param string $name
     */
    public function profilerStop($name)
    {
        if (null !== $this->stopwatch) {
            $stopwatch = $this->getStopwatch();
            $stopwatch->stop($name);
        }
    }
}
