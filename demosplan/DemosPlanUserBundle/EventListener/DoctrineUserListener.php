<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use Exception;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;

class DoctrineUserListener
{
    /**
     * @var CustomerService
     */
    protected $customerService;
    /**
     * @var array<int, string>
     */
    protected $rolesAllowed;

    /**
     * @param GlobalConfigInterface|GlobalConfig $globalConfig
     */
    public function __construct(CustomerService $customerService, GlobalConfigInterface $globalConfig)
    {
        $this->customerService = $customerService;
        $this->rolesAllowed = $globalConfig->getRolesAllowed();
    }

    public function postLoad(User $user)
    {
        try {
            $customer = $this->customerService->getCurrentCustomer();
            $user->setCurrentCustomer($customer);
            $user->setRolesAllowed($this->rolesAllowed);
        } catch (Exception $e) {
            // bad luck :-(
        }
    }
}
