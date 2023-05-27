<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Symfony\Contracts\Service\Attribute\Required;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class CoreService
{
    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var ManagerRegistry */
    protected $doctrine;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return Stopwatch
     */
    public function getStopwatch()
    {
        return $this->stopwatch;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
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

    /**
     * @return ManagerRegistry
     */
    public function getDoctrine()
    {
        return $this->doctrine;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setDoctrine(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }
}
