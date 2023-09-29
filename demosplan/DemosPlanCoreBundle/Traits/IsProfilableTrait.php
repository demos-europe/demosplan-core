<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits;

use Symfony\Component\Stopwatch\Stopwatch;

/**
 * This trait contains all the methods we use to control and augment
 * symfony's profiler.
 */
trait IsProfilableTrait
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

    /**
     * @param Stopwatch $stopwatch
     */
    public function setStopwatch($stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    /**
     * Starte den Profiler, Anzeige in der Symfony Toolbar.
     *
     * @param string $name
     */
    protected function profilerStart($name)
    {
        if (null === $this->stopwatch) {
            $this->stopwatch = new Stopwatch(true);
        }

        $stopwatch = $this->getStopwatch();
        $stopwatch->start($name);
    }

    /**
     * Stoppe den Profiler.
     *
     * @param string $name
     */
    protected function profilerStop($name)
    {
        if (null !== $this->stopwatch) {
            $stopwatch = $this->getStopwatch();
            $stopwatch->stop($name);
        }
    }
}
