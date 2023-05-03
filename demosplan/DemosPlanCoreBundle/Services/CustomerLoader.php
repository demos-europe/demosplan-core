<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ValueObject\CustomerValueObject;

class CustomerLoader
{
    /** @var CustomerHandler */
    private $customerHandler;

    /** @var GlobalConfig */
    private $globalConfig;

    public function __construct(CustomerHandler $customerHandler, GlobalConfig $globalConfig)
    {
        $this->customerHandler = $customerHandler;
        $this->globalConfig = $globalConfig;
    }

    /**
     * @return CustomerValueObject|null
     *
     * @throws CustomerNotFoundException
     */
    public function getCustomerObject()
    {
        $customerObject = null;
        $customer = $this->getCustomer();
        if (null !== $customer) {
            $customerObject = new CustomerValueObject();
            $customerObject->setId($customer->getId());
            $customerObject->setName($customer->getName());
            $customerObject->lock();
        }

        return $customerObject;
    }

    /**
     * @return Customer|null
     *
     * @throws CustomerNotFoundException
     */
    private function getCustomer()
    {
        $subdomain = $this->globalConfig->getSubdomain();

        return $this->customerHandler->findCustomerBySubdomain($subdomain);
    }
}
