<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\CustomerServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ResetInterface;

class CustomerService implements CustomerServiceInterface, ResetInterface
{
    /**
     * Subdomain lookups go through the entity persister and bypass Doctrine's identity map, so
     * without this every call - notably getCurrentCustomer() - issues another query. Ids are
     * cached instead of entities so that the returned customer is always a managed one, even
     * after the entity manager has been cleared.
     *
     * @var array<string, string>
     */
    private array $customerIdsBySubdomain = [];

    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Drops the cached ids, e.g. between messages handled by a long running worker.
     */
    public function reset(): void
    {
        $this->customerIdsBySubdomain = [];
    }

    public function findCustomerById(string $id): CustomerInterface
    {
        return $this->customerRepository->findCustomerById($id);
    }

    /**
     * @return list<Customer>
     */
    public function findAll(): array
    {
        return $this->customerRepository->findAll();
    }

    public function findCustomersByIds(array $ids): array
    {
        return $this->customerRepository->findCustomersByIds($ids);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function findCustomerBySubdomain(string $subdomain): CustomerInterface
    {
        $customerId = $this->customerIdsBySubdomain[$subdomain]
            ??= $this->customerRepository->findCustomerBySubdomain($subdomain)->getId();

        return $this->customerRepository->findCustomerById($customerId);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function getCurrentCustomer(): CustomerInterface
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
    public function updateCustomer(CustomerInterface $customer): CustomerInterface
    {
        $this->reset();

        return $this->customerRepository->updateObject($customer);
    }

    /**
     * @throws ORMException
     * @throws ViolationsException
     */
    public function createCustomer(string $name, string $subdomain): CustomerInterface
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
     * @return list<array{0: string, 1: string}> list of tuples with the first entry being the name
     *                                           and the second one being the subdomain
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
