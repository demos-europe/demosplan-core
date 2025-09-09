<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Tests\Base\FunctionalTestCase;

/**
 * Class CustomersPublicAffairsAgencyTest.
 *
 * @group UnitTest
 */
class CustomersPublicAffairsAgencyTest extends FunctionalTestCase
{
    /**
     * @var UserHandler
     */
    protected $userHandler;

    /**
     * @var CustomerHandler
     */
    protected $customerHandler;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->userHandler = self::getContainer()->get(UserHandler::class);
        $this->customerHandler = self::getContainer()->get(CustomerHandler::class);
    }

    /**
     * @throws Exception
     */
    public function testCustomers()
    {
        self::markSkippedForCIIntervention();

        $paaOrga = $this->getReference('testOrgaFP');
        $paaOrga2 = $this->getReference('testOrgaInvitableInstitution');
        $nonPaaOrga = $this->getReference('testOrgaPB');
        $customerRostock = $this->getReference('Rostock');
        $customerBrandenburg = $this->getReference('Brandenburg');

        $this->assertOrgaHasNoCustomers($paaOrga);
        $this->assertOrgaHasNoCustomers($nonPaaOrga);
        $this->assertCustomerHasNoPaa($customerRostock);
        $this->assertAddCustomerToNonPaaOrga($customerRostock, $nonPaaOrga);
        $this->assertAddCustomerToPaa($customerRostock, $paaOrga);
        $this->assertAddRepeatedPaaToCustomer($customerRostock, $paaOrga);
        $this->assertAddCustomerToPaa($customerRostock, $paaOrga2);
        $this->assertAddCustomerToPaa($customerBrandenburg, $paaOrga2);
        $this->assertRemoveCustomerFromPaaOrga($customerRostock, $paaOrga2);
        $this->assertRemoveCustomerFromNonExistingPaaOrga($customerRostock, $paaOrga2);
    }

    public function assertRemoveCustomerFromNonExistingPaaOrga(Customer $customer, Orga $orga)
    {
        $orgaCustomersCount = $orga->getCustomers()->count();
        $customerOrgasCount = $customer->getOrgas()->count();
        $this->assertOrgaHasCustomers($orgaCustomersCount, $orga);
        $this->assertCustomerHasPaas($customerOrgasCount, $customer);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function assertRemoveCustomerFromPaaOrga(Customer $customer, Orga $orga)
    {
        $orgaCustomersCount = $orga->getCustomers()->count();
        $customerOrgasCount = $customer->getOrgas()->count();
        $this->userHandler->removeCustomerFromPublicAffairsAgencyByIds($customer->getId(), $orga->getId());
        $this->assertOrgaHasCustomers($orgaCustomersCount - 1, $orga);
        $this->assertCustomerHasPaas($customerOrgasCount - 1, $customer);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function assertAddCustomerToNonPaaOrga(Customer $customer, Orga $orga)
    {
        $orgaCustomersCount = $orga->getCustomers()->count();
        $customerOrgasCount = $customer->getOrgas()->count();
        try {
            $this->userHandler->addCustomerToPublicAffairsAgencyByIds($customer->getId(), $orga->getId());
        } catch (InvalidArgumentException $e) {
            $this->assertOrgaHasCustomers($orgaCustomersCount, $orga);
            $this->assertCustomerHasPaas($customerOrgasCount, $customer);
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function assertAddCustomerToPaa(Customer $customer, Orga $orga)
    {
        $orgaCustomersCount = $orga->getCustomers()->count();
        $customerOrgasCount = $customer->getOrgas()->count();
        $this->userHandler->addCustomerToPublicAffairsAgencyByIds($customer->getId(), $orga->getId());
        $this->assertOrgaHasCustomers($orgaCustomersCount + 1, $orga);
        $this->assertCustomerHasPaas($customerOrgasCount + 1, $customer);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function assertAddRepeatedPaaToCustomer(Customer $customer, Orga $orga)
    {
        $orgaCustomersCount = $orga->getCustomers()->count();
        $customerOrgasCount = $customer->getOrgas()->count();
        try {
            $this->userHandler->addCustomerToPublicAffairsAgencyByIds($customer->getId(), $orga->getId());
        } catch (InvalidArgumentException $e) {
            $this->assertOrgaHasCustomers($orgaCustomersCount, $orga);
            $this->assertCustomerHasPaas($customerOrgasCount, $customer);
        }
    }

    public function assertOrgaHasNoCustomers(Orga $orga)
    {
        static::assertEmpty($orga->getCustomers());
    }

    public function assertCustomerHasNoPaa(Customer $customer)
    {
        static::assertEmpty($this->userHandler->findPublicAffairsAgenciesIdsByCustomer($customer));
    }

    public function assertOrgaHasCustomers(int $expected, Orga $orga)
    {
        static::assertCount($expected, $orga->getCustomers());
    }

    public function assertCustomerHasPaas(int $expected, Customer $customer)
    {
        static::assertCount($expected, $this->userHandler->findPublicAffairsAgenciesIdsByCustomer($customer));
    }
}
