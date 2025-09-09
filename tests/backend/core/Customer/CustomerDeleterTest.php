<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Customer;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\Customer\CustomerDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class CustomerDeleterTest extends FunctionalTestCase
{
    /** @var array<int, Customer|Proxy>|null */
    private ?array $testCustomers;
    private null|Customer|Proxy $testCustomerToDelete;
    /** @var CustomerDeleter */
    protected $sut;
    private ?SqlQueriesService $queriesService = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(CustomerDeleter::class);
        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->testCustomers = CustomerFactory::createMany(5);
        $this->testCustomerToDelete = reset($this->testCustomers);
    }

    public function testDeleteCustomer(): void
    {
        $this->assertTestCustomersExistsInDataBase();
        $this->sut->deleteCustomer($this->testCustomerToDelete->getId(), false);
        $this->assertCustomerIsNotInDatabase($this->testCustomerToDelete->getId());
    }

    private function assertTestCustomersExistsInDataBase(): void
    {
        $customersInDataBase = $this->getEntries(Customer::class);
        foreach ($this->testCustomers as $customerToTest) {
            $match = array_filter(
                $customersInDataBase,
                static fn (Customer|Proxy $customer): bool => $customer->getId() === $customerToTest->getId()
            );
            self::assertCount(1, $match, 'CustomerFixture not found in test Database');
        }
    }

    private function assertCustomerIsNotInDatabase(string $customerId): void
    {
        $customersInDataBase = $this->getEntries(Customer::class);
        $match = array_filter(
            $customersInDataBase,
            static fn (Customer|Proxy $customer): bool => $customer->getId() === $customerId
        );
        self::assertCount(0, $match, 'CustomerFixture still found in Database');
    }
}
