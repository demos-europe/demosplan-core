<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use demosplan\DemosPlanCoreBundle\Event\Procedure\EventConcern;

class CriticalConcernException extends DemosException
{
    /**
     * @param array<string, array<int, EventConcern>> $concerns
     */
    public function __construct(string $userMsg, private readonly array $concerns)
    {
        parent::__construct($userMsg);
    }

    /**
     * @return array<string, array<int, EventConcern>>
     */
    public function getConcerns(): array
    {
        return $this->concerns;
    }
}
