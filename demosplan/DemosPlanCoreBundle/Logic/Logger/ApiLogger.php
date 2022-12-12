<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Logger;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Logger\ApiLoggerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use Psr\Log\LoggerInterface;

class ApiLogger implements LoggerInterface, ApiLoggerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var string
     */
    private $environment;

    public function __construct(
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        MessageBagInterface $messageBag
    ) {
        $this->logger = $logger;
        $this->messageBag = $messageBag;
        $this->environment = $globalConfig->getKernelEnvironment();
    }

    public function warning($message, array $context = [])
    {
        $message = "Faulty API request: $message";

        $this->logger->warning($message, $context);

        // only add DX hint in dev environment
        if (DemosPlanKernel::ENVIRONMENT_DEV === $this->environment) {
            $this->messageBag->add('dev', $message);
        }
    }

    public function emergency($message, array $context = [])
    {
        $this->logger->emergency($message, $context);
    }

    public function alert($message, array $context = [])
    {
        $this->logger->alert($message, $context);
    }

    public function critical($message, array $context = [])
    {
        $this->logger->critical($message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->logger->error($message, $context);
    }

    public function notice($message, array $context = [])
    {
        $this->logger->notice($message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->logger->info($message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, $context);
    }
}
