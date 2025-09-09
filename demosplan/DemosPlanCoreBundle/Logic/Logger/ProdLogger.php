<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Logger;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Custom logger that logs only in production environment.
 */
class ProdLogger implements LoggerInterface
{
    /**
     * @var string
     */
    private $environment;

    public function __construct(private readonly LoggerInterface $logger, ParameterBagInterface $parameterBag)
    {
        $this->environment = $parameterBag->get('kernel.environment');
    }

    public function log($level, $message, array $context = []): void
    {
        if (DemosPlanKernel::ENVIRONMENT_PROD === $this->environment) {
            $this->logger->log($level, $message, $context);
        }
    }

    public function emergency($message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}
