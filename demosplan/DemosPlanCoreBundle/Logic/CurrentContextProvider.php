<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\CurrentContextProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserService;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use Symfony\Component\Security\Core\User\UserInterface;

class CurrentContextProvider implements CurrentContextProviderInterface
{
    private CurrentProcedureService $currentProcedureProvider;

    private CustomerService $currentCustomerProvider;

    private CurrentUserService $currentUserProvider;

    public function __construct(
        CurrentProcedureService $currentProcedureProvider,
        CustomerService $currentCustomerProvider,
        CurrentUserService $currentUserProvider
    ) {
        $this->currentProcedureProvider = $currentProcedureProvider;
        $this->currentCustomerProvider = $currentCustomerProvider;
        $this->currentUserProvider = $currentUserProvider;
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
