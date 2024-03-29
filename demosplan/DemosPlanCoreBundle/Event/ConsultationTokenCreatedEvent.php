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

use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;

class ConsultationTokenCreatedEvent extends DPlanEvent
{
    public function __construct(private readonly ConsultationToken $consultationToken)
    {
    }

    public function getConsultationToken(): ConsultationToken
    {
        return $this->consultationToken;
    }
}
