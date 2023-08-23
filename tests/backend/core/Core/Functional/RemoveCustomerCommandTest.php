<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;


use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\CustomerCounty;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Story\FullCustomerStory;
use demosplan\DemosPlanCoreBundle\Story\OrgaHasMultipleRelatedCustomerStory;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class RemoveCustomerCommandTest extends FunctionalTestCase
{
    use CommandTesterTrait;

    /**
     * Related ReportEntries should be deleted.
     * Reports of a customer are not identfied by the "customter" field of a report,
     * but by the procedure(Id) created by the orga which is related to a customer to delete.
     *
     */
    public function testReportsOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        /** @var Customer[] $customers */
        $customers = $this->getEntries(Customer::class, ['name' => FullCustomerStory::NAME]);
        static::assertCount(1, $customers);
        static::assertInstanceOf(Customer::class, $customers[0]);
        /** @var Customer $customer */
        $customer = $customers[0];
        $testCustomerId = $customers[0]->getId();

        $relatedReports = [];
        static::assertNotEmpty($customer->getCustomerCounties());
        static::assertNotEmpty($customer->getOrgaStatuses());
        static::assertNotEmpty($customer->getOrgas());

        foreach ($customer->getOrgas() as $orga) {
            static::assertNotEmpty($orga->getProcedures());
            foreach ($orga->getProcedures() as $procedure) {
                $relatedReports = array_merge(
                    $relatedReports,
                    $this->getEntries(ReportEntry::class, ['identifier' => $procedure->getId()])
                );
            }
        }

        static::assertNotEmpty($relatedReports);
        static::assertInstanceOf(ReportEntry::class, $relatedReports[0]);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $relatedReports = $this->getEntries(ReportEntry::class, ['customer' => $testCustomerId]);
        static::assertEmpty($relatedReports);
    }

    /**
     * Related Blueprints should be deleted.
     */
    public function testBlueprintOnDeleteCustomer(): void
    {
        FullCustomerStory::load();/** @var Customer[] $customers */
        $customers = $this->getEntries(Customer::class, ['name' => FullCustomerStory::NAME]);
        static::assertNotEmpty($customers);
        static::assertInstanceOf(Customer::class, $customers[0]);
        $testCustomerId = $customers[0]->getId();

        /** @var Procedure[] $relatedBlueprints */
        $relatedBlueprints = $this->getEntries(Procedure::class, ['customer' => $testCustomerId]);
        static::assertNotEmpty($relatedBlueprints);
        static::assertInstanceOf(Procedure::class, $relatedBlueprints[0]);
        static::assertTrue($relatedBlueprints[0]->isCustomerMasterBlueprint());

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $relatedBlueprint = $this->getEntries(Procedure::class, ['customer' => $testCustomerId]);
        static::assertEmpty($relatedBlueprint);
    }

    /**
     * Related procedures should be deleted.
     */
    public function testProcedureOnDeleteCustomer()
    {
        // todo
    }

    /**
     * Relation from customer to counties should be deleted.
     */
    public function testCustomerCountiesOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $customers = $this->getEntries(Customer::class, ['name' => FullCustomerStory::NAME]);
        static::assertNotEmpty($customers);
        static::assertInstanceOf(Customer::class, $customers[0]);
        $testCustomer = $customers[0];
        $testCustomerId = $testCustomer->getId();

        $relatedCustomerCounties = $this->getEntries(CustomerCounty::class, ['customer' => $testCustomerId]);
        static::assertNotEmpty($relatedCustomerCounties);
        static::assertInstanceOf(CustomerCounty::class, $relatedCustomerCounties[0]);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $relatedCustomerCounties = $this->getEntries(CustomerCounty::class, ['customer' => $testCustomerId]);
        static::assertEmpty($relatedCustomerCounties);
    }

    /**
     * Related counties should not be deleted.
     */
    public function testCountiesOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $totalAmountOfCountiesBeforeDeletion = $this->countEntries(County::class);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        static::assertSame($totalAmountOfCountiesBeforeDeletion, $this->countEntries(County::class));
    }

    /**
     * Related user should not be deleted.
     */
    public function testUsersOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $totalAmountOfUsersBeforeDeletion = $this->countEntries(User::class);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        static::assertSame($totalAmountOfUsersBeforeDeletion, $this->countEntries(User::class));
    }

    /**
     * Related roles should not be deleted.
     */
    public function testRolesOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $totalAmountOfRolesBeforeDeletion = $this->countEntries(Role::class);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        static::assertSame($totalAmountOfRolesBeforeDeletion, $this->countEntries(Role::class));
    }

    /**
     * Related orgas should be deleted, in case of the only relation of the orgas to a customer is,
     * to the customer which will be deleted.'
     */
    public function testOrgasOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $totalAmountOfOrgasBeforeDeletion = $this->countEntries(Orga::class);
        /** @var Customer[] $customers */
        $customers = $this->getEntries(Customer::class, ['name' => FullCustomerStory::NAME]);
        static::assertCount(2, $customers[0]->getOrgaStatuses());
        static::assertCount(2, $customers[0]->getOrgas());

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        static::assertSame($totalAmountOfOrgasBeforeDeletion - 2, $this->countEntries(Orga::class));
    }

    /**
     * Throw an exception in case of a related orga has more than one customer!
     */
    public function testExceptionOnDeleteCustomer(): void
    {
        $this->expectException(DemosException::class);

        OrgaHasMultipleRelatedCustomerStory::load();
        $totalAmountOfOrgasBeforeDeletion = $this->countEntries(Orga::class);


        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        static::assertSame($totalAmountOfOrgasBeforeDeletion, $this->countEntries(Orga::class));
    }

    /**
     * Customer itself should be deleted.
     */
    public function testCustomerOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $totalAmountOfCustomersBeforeDeletion = $this->countEntries(Customer::class);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        static::assertSame($totalAmountOfCustomersBeforeDeletion - 1, $this->countEntries(Customer::class));
    }

    /**
     * Relation between customer and roles (of user) should be deleted.
     */
    public function testUserRoleInCustomerOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $customers = $this->getEntries(Customer::class, ['name' => FullCustomerStory::NAME]);
        static::assertNotEmpty($customers);
        static::assertInstanceOf(Customer::class, $customers[0]);
        $testCustomer = $customers[0];
        $testCustomerId = $testCustomer->getId();

        $relatedRoleInCustomers = $this->getEntries(UserRoleInCustomer::class, ['customer' => $testCustomerId]);
        static::assertNotEmpty($relatedRoleInCustomers);
        static::assertInstanceOf(UserRoleInCustomer::class, $relatedRoleInCustomers[0]);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $relatedRoleInCustomers = $this->getEntries(UserRoleInCustomer::class, ['customer' => $testCustomerId]);
        static::assertEmpty($relatedRoleInCustomers);
    }

    /**
     * Relation between customer and orgaTypes should be deleted.
     */
    public function testOrgaStatusInCustomerOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        $customers = $this->getEntries(Customer::class, ['name' => FullCustomerStory::NAME]);
        static::assertNotEmpty($customers);
        static::assertInstanceOf(Customer::class, $customers[0]);
        $testCustomer = $customers[0];
        $testCustomerId = $testCustomer->getId();

        $relatedOrgaStatusInCustomers = $this->getEntries(OrgaStatusInCustomer::class, ['customer' => $testCustomerId]);
        static::assertNotEmpty($relatedOrgaStatusInCustomers);
        static::assertInstanceOf(OrgaStatusInCustomer::class, $relatedOrgaStatusInCustomers[0]);

        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([FullCustomerStory::NAME]);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $relatedOrgaStatusInCustomers = $this->getEntries(OrgaStatusInCustomer::class, ['customer' => $testCustomerId]);
        static::assertEmpty($relatedOrgaStatusInCustomers);
    }

    private function getCommandTester(): CommandTester
    {
        return $this->getCommandTesterByName(self::bootKernel(), 'dplan:data:remove-customer');
    }
}
