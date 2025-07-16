<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Services\InitializeServiceInterface;
use Psr\Log\LoggerInterface;

class InitializeService implements InitializeServiceInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @deprecated checkPermissionListener is used to perform initial Permission checks
     */
    public function initialize(array $context): void
    {
        $this->logger->warning('Call to deprecated InitializeService', ['trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)]);
    }
}
