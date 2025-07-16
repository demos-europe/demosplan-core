<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits\DI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait RequiresLoggerTrait
{
    protected ?LoggerInterface $logger = null;

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    #[Required]
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }
}
