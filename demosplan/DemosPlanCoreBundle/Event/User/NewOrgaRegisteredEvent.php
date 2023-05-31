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
    /**
     * @var string
     */
    private $userEmail;

    /**
     * @var array
     */
    private $orgaTypeNames;

    /**
     * @var string
     */
    private $customerName;

    /**
     * @var string
     */
    private $userFirstName;

    /**
     * @var string
     */
    private $userLastName;

    /**
     * @var string
     */
    private $orgaName;

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

    public function __construct(
        string $userEmail,
        array $orgaTypeNames,
        string $customerName,
        string $userFirstName,
        string $userLastName,
        string $orgaName
    ) {
        $this->userEmail = $userEmail;
        $this->orgaTypeNames = $orgaTypeNames;
        $this->customerName = $customerName;
        $this->userFirstName = $userFirstName;
        $this->userLastName = $userLastName;
        $this->orgaName = $orgaName;
    }
}
