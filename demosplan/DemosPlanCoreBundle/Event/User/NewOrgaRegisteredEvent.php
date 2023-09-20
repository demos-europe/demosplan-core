<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\User;

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class NewOrgaRegisteredEvent extends DPlanEvent
{
    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

    public function getOrgaTypeNames(): array
    {
        return $this->orgaTypeNames;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getUserFirstName(): string
    {
        return $this->userFirstName;
    }

    public function getUserLastName(): string
    {
        return $this->userLastName;
    }

    public function getOrgaName(): string
    {
        return $this->orgaName;
    }

    public function __construct(private readonly string $userEmail, private readonly array $orgaTypeNames, private readonly string $customerName, private readonly string $userFirstName, private readonly string $userLastName, private readonly string $orgaName)
    {
    }
}
