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

class NewOrgaCreatedEvent extends DPlanEvent
{
    protected OrgaInterface $orga;

    protected ?bool $canCreateProcedures;

    public function __construct(Orga $newOrga, ?bool $canCreateProcedures)
    {
        $this->orga = $newOrga;
        $this->canCreateProcedures = $canCreateProcedures;
    }

    public function getOrganisation(): OrgaInterface
    {
        return $this->orga;
    }

    public function canCreateProcedures(): ?bool
    {
        return $this->canCreateProcedures;
    }
}
