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

use DemosEurope\DemosplanAddon\Contracts\Events\GetEmailIdsEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event will be catched and handled in the addons. It allows to retrieve emails ids used by the addons.
 */
class GetEmailIdsEvent extends Event implements GetEmailIdsEventInterface
{
    private array $emailIds;

    public function getEmailIds(): array
    {
        return $this->emailIds;
    }

    public function addEmailIds(array $addonEmailIds): void
    {
        if (0 !== count($addonEmailIds)) {
            foreach ($addonEmailIds as $addonEmailId) {
                $this->emailIds[] = $addonEmailId;
            }
        }
    }
}
