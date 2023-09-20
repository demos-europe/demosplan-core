<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use Doctrine\ORM\Mapping\PostLoad;

class CountyEntityListener
{
    /**
     * @var Customer
     */
    private $currentCustomer;

    /**
     * @throws CustomerNotFoundException
     */
    public function __construct(CustomerService $customerService)
    {
        $this->currentCustomer = $customerService->getCurrentCustomer();
    }

    /**
     * @PostLoad
     */
    public function postLoad(County $county): void
    {
        foreach ($county->getCustomerCounties() as $customerCounty) {
            if ($customerCounty->getCustomer() === $this->currentCustomer) {
                $county->setEmail($customerCounty->getEmail());
            }
        }
    }
}
