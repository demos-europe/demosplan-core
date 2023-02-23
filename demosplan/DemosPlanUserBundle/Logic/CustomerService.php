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
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Repository\CustomerRepository;
use demosplan\DemosPlanUserBundle\Repository\UserRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ValidatorInterface $validator
    ) {
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

    /**
     * @throws ORMException
     * @throws ViolationsException
     */
    public function createCustomer(string $name, string $subdomain): Customer
    {
        $customer = new Customer($name, $subdomain);
        $violations = $this->validator->validate($customer);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $this->customerRepository->persistEntities([$customer]);

        return $customer;
    }

    /**
     * @return list<array{0: string, 1: string}> list of tuples with the first entry being the name and the second one being the subdomain
     */
    public function getReservedCustomerNamesAndSubdomains(): array
    {
        $existingCustomers = $this->customerRepository->findAll();

        return array_map(
            static fn (Customer $customer): array => [$customer->getName(), $customer->getSubdomain()],
            $existingCustomers
        );
    }
}
