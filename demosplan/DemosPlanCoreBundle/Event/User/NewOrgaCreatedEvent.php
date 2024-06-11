<?php

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

class NewOrgaCreatedEvent extends DPlanEvent
{

    protected OrgaInterface $orga;
    public function __construct(Orga $newOrga) {
        $this->orga = $newOrga;
    }

    public function getOrganisation(): OrgaInterface
    {
        return $this->orga;
    }
}
