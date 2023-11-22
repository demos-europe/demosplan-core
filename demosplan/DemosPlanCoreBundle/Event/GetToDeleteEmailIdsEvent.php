<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use DemosEurope\DemosplanAddon\Contracts\Events\GetToDeleteEmailIdsEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

class GetToDeleteEmailIdsEvent extends Event implements GetToDeleteEmailIdsEventInterface
{
    private readonly array $toDeleteEmailIds;

    public function getToDeleteEmailIds(): array
    {
        return $this->toDeleteEmailIds;
    }

    public function setToDeleteEmailIds(array $emailIds): void
    {
        $this->toDeleteEmailIds = $emailIds;
    }
}
