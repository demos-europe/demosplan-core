<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\UnitTestCase;

class CustomerServiceTest extends UnitTestCase
{
    protected ?CustomerService $sut = null;

    /** @var CustomerRepository&MockObject|null */
    protected ?MockObject $customerRepository = null;

    /** @var GlobalConfigInterface&MockObject|null */
    protected ?MockObject $globalConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);

        $this->sut = new CustomerService(
            $this->customerRepository,
            $this->globalConfig,
            $this->createMock(ValidatorInterface::class)
        );
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function testFindCustomerBySubdomainQueriesTheSubdomainOnlyOnce(): void
    {
        // Arrange
        $customer = $this->createCustomerMock('customer-id', 'brandenburg');
        $this->customerRepository->expects(self::once())
            ->method('findCustomerBySubdomain')
            ->with('brandenburg')
            ->willReturn($customer);
        $this->customerRepository->method('findCustomerById')
            ->with('customer-id')
            ->willReturn($customer);

        // Act
        $first = $this->sut->findCustomerBySubdomain('brandenburg');
        $second = $this->sut->findCustomerBySubdomain('brandenburg');

        // Assert
        self::assertSame($customer, $first);
        self::assertSame($customer, $second);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function testFindCustomerBySubdomainResolvesTheCachedIdThroughTheIdentityMap(): void
    {
        // Arrange
        $detachedCustomer = $this->createCustomerMock('customer-id', 'brandenburg');
        $managedCustomer = $this->createCustomerMock('customer-id', 'brandenburg');
        $this->customerRepository->method('findCustomerBySubdomain')->willReturn($detachedCustomer);
        $this->customerRepository->expects(self::exactly(2))
            ->method('findCustomerById')
            ->with('customer-id')
            ->willReturn($managedCustomer);

        // Act
        $first = $this->sut->findCustomerBySubdomain('brandenburg');
        $second = $this->sut->findCustomerBySubdomain('brandenburg');

        // Assert
        self::assertSame($managedCustomer, $first);
        self::assertSame($managedCustomer, $second);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function testGetCurrentCustomerSharesTheCacheWithFindCustomerBySubdomain(): void
    {
        // Arrange
        $customer = $this->createCustomerMock('customer-id', 'brandenburg');
        $this->globalConfig->method('getSubdomain')->willReturn('brandenburg');
        $this->customerRepository->expects(self::once())
            ->method('findCustomerBySubdomain')
            ->willReturn($customer);
        $this->customerRepository->method('findCustomerById')->willReturn($customer);

        // Act
        $this->sut->getCurrentCustomer();
        $this->sut->getCurrentCustomer();
        $this->sut->findCustomerBySubdomain('brandenburg');

        // Assert
        self::assertSame('brandenburg', $this->sut->getCurrentCustomer()->getSubdomain());
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function testDistinctSubdomainsAreCachedSeparately(): void
    {
        // Arrange
        $brandenburg = $this->createCustomerMock('brandenburg-id', 'brandenburg');
        $hamburg = $this->createCustomerMock('hamburg-id', 'hamburg');
        $this->customerRepository->expects(self::exactly(2))
            ->method('findCustomerBySubdomain')
            ->willReturnMap([
                ['brandenburg', $brandenburg],
                ['hamburg', $hamburg],
            ]);
        $this->customerRepository->method('findCustomerById')
            ->willReturnMap([
                ['brandenburg-id', $brandenburg],
                ['hamburg-id', $hamburg],
            ]);

        // Act
        $this->sut->findCustomerBySubdomain('brandenburg');
        $this->sut->findCustomerBySubdomain('hamburg');

        // Assert
        self::assertSame($brandenburg, $this->sut->findCustomerBySubdomain('brandenburg'));
        self::assertSame($hamburg, $this->sut->findCustomerBySubdomain('hamburg'));
    }

    /**
     * @throws CustomerNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testUpdateCustomerDropsTheCache(): void
    {
        // Arrange
        $customer = $this->createCustomerMock('customer-id', 'brandenburg');
        $this->customerRepository->expects(self::exactly(2))
            ->method('findCustomerBySubdomain')
            ->willReturn($customer);
        $this->customerRepository->method('findCustomerById')->willReturn($customer);
        $this->customerRepository->method('updateObject')->willReturn($customer);

        // Act
        $this->sut->findCustomerBySubdomain('brandenburg');
        $this->sut->updateCustomer($customer);

        // Assert the second lookup hits the repository again, see the expectation above
        self::assertSame($customer, $this->sut->findCustomerBySubdomain('brandenburg'));
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function testResetDropsTheCache(): void
    {
        // Arrange
        $customer = $this->createCustomerMock('customer-id', 'brandenburg');
        $this->customerRepository->expects(self::exactly(2))
            ->method('findCustomerBySubdomain')
            ->willReturn($customer);
        $this->customerRepository->method('findCustomerById')->willReturn($customer);

        // Act
        $this->sut->findCustomerBySubdomain('brandenburg');
        $this->sut->reset();

        // Assert the second lookup hits the repository again, see the expectation above
        self::assertSame($customer, $this->sut->findCustomerBySubdomain('brandenburg'));
    }

    private function createCustomerMock(string $id, string $subdomain): Customer
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn($id);
        $customer->method('getSubdomain')->willReturn($subdomain);

        return $customer;
    }
}
