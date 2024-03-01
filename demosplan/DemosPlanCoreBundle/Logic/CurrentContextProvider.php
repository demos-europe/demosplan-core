<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\CurrentContextProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use Symfony\Component\Security\Core\User\UserInterface;

class CurrentContextProvider implements CurrentContextProviderInterface
{
    public function __construct(
        private readonly CurrentProcedureService $currentProcedureProvider,
        private readonly CustomerService $currentCustomerProvider,
        private readonly CurrentUserService $currentUserProvider
    ) {
    }

    public function getCurrentProcedure(): ?ProcedureInterface
    {
        return $this->currentProcedureProvider->getProcedure();
    }

    public function getCurrentUser(): UserInterface
    {
        return $this->currentUserProvider->getUser();
    }

    public function getCurrentCustomer(): CustomerInterface
    {
        return $this->currentCustomerProvider->getCurrentCustomer();
    }
}
