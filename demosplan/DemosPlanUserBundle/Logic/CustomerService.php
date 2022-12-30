<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Repository\CustomerRepository;

class CustomerService
{
    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(CustomerRepository $customerRepository, GlobalConfigInterface $globalConfig)
    {
        $this->customerRepository = $customerRepository;
        $this->globalConfig = $globalConfig;
    }

    public function findCustomerById(string $id): Customer
    {
        return $this->customerRepository->findCustomerById($id);
    }

    public function findCustomersByIds(array $ids): array
    {
        return $this->customerRepository->findCustomersByIds($ids);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function findCustomerBySubdomain(string $subdomain): Customer
    {
        return $this->customerRepository->findCustomerBySubdomain($subdomain);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function getCurrentCustomer(): Customer
    {
        $subdomain = $this->globalConfig->getSubdomain();

        return $this->findCustomerBySubdomain($subdomain);
    }

    /**
     * @return Customer updated Customer
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateCustomer(Customer $customer): Customer
    {
        return $this->customerRepository->updateObject($customer);
    }
}
