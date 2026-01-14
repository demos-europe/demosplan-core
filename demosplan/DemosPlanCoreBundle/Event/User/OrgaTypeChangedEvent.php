<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

/**
 * Event dispatched when an organization's types have been updated.
 * Contains the updated organization and the old organization types before the update.
 */
class OrgaTypeChangedEvent extends DPlanEvent
{
    /**
     * @param Orga          $updatedOrga  The organization after the update
     * @param array<string> $oldOrgaTypes Array of old organization type names before update (e.g., ['OLAUTH', 'OPAUTH'])
     */
    public function __construct(
        protected OrgaInterface $updatedOrga,
        protected array $oldOrgaTypes,
    ) {
    }

    public function getUpdatedOrga(): OrgaInterface
    {
        return $this->updatedOrga;
    }

    /**
     * Get the old organization type names before the update.
     *
     * @return array<string> Array of type names like ['OLAUTH', 'OPAUTH', ...]
     */
    public function getOldOrgaTypes(): array
    {
        return $this->oldOrgaTypes;
    }
}
