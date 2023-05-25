<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class OrgaEditedEvent extends DPlanEvent
{
    /**
     * Orga status before Update.
     *
     * @var Orga
     */
    protected $organisationBefore;
    /**
     * Orga status after Update.
     *
     * @var Orga
     */
    protected $organisationUpdated;

    public function __construct(
        Orga $organisationBefore,
        Orga $organisationUpdated
    ) {
        $this->organisationUpdated = $organisationUpdated;
        $this->organisationBefore = $organisationBefore;
    }

    /**
     * @return Orga
     */
    public function getOrganisationBefore()
    {
        return $this->organisationBefore;
    }

    /**
     * @return Orga
     */
    public function getOrganisationUpdated()
    {
        return $this->organisationUpdated;
    }
}
