<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Customer;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Permission\UserAccessControlFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedurePhaseDefinitionFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControl;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\Customer\CustomerDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class CustomerDeleterTest extends FunctionalTestCase
{
    /** @var array<int, Customer|Proxy>|null */
    private ?array $testCustomers;
    private Customer|Proxy|null $testCustomerToDelete;
    private ?Customer $customerToDelete;
    private ?Customer $otherCustomer;
    /** @var CustomerDeleter */
    protected $sut;
    private ?SqlQueriesService $queriesService = null;
    private ?RoleHandler $roleHandler = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(CustomerDeleter::class);
        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $this->testCustomers = CustomerFactory::createMany(5);
        $this->testCustomerToDelete = reset($this->testCustomers);
        $this->customerToDelete = $this->testCustomerToDelete->_real();
        $this->otherCustomer = $this->testCustomers[1]->_real();
    }

    public function testDeleteCustomer(): void
    {
        $this->assertTestCustomersExistsInDataBase();
        $this->sut->deleteCustomer($this->testCustomerToDelete->getId(), false);
        $this->assertCustomerIsNotInDatabase($this->testCustomerToDelete->getId());
    }

    public function testDeleteCustomerRemovesCustomerScopedData(): void
    {
        // Arrange
        $customerPhaseDefinition = ProcedurePhaseDefinitionFactory::createOne(['customer' => $this->customerToDelete])->_real();
        $otherCustomersPhaseDefinition = ProcedurePhaseDefinitionFactory::createOne(['customer' => $this->otherCustomer])->_real();
        // global definitions (customer_id NULL) must survive the deletion
        $globalPhaseDefinition = ProcedurePhaseDefinitionFactory::createOne()->_real();

        $accessControlPermission = $this->createAccessControlEntry($this->customerToDelete);
        $otherCustomersAccessControlPermission = $this->createAccessControlEntry($this->otherCustomer);

        $userAccessControl = $this->createUserAccessControlEntry($this->customerToDelete);
        $otherCustomersUserAccessControl = $this->createUserAccessControlEntry($this->otherCustomer);

        $reportEntry = $this->createReportEntry($this->customerToDelete);
        $otherCustomersReportEntry = $this->createReportEntry($this->otherCustomer);

        // Act
        $this->sut->deleteCustomer($this->testCustomerToDelete->getId(), false);

        // Assert: data scoped to the deleted customer is gone
        self::assertCount(0, $this->getEntries(ProcedurePhaseDefinition::class, ['id' => $customerPhaseDefinition->getId()]));
        self::assertCount(0, $this->getEntriesWhereInIds(AccessControl::class, [$accessControlPermission->getId()]));
        self::assertCount(0, $this->getEntriesWhereInIds(UserAccessControl::class, [$userAccessControl->getId()]));
        self::assertCount(0, $this->getEntries(ReportEntry::class, ['identifier' => $reportEntry->getIdentifier()]));

        // Assert: global and other customers' data is kept
        self::assertCount(1, $this->getEntries(ProcedurePhaseDefinition::class, ['id' => $globalPhaseDefinition->getId()]));
        self::assertCount(1, $this->getEntries(ProcedurePhaseDefinition::class, ['id' => $otherCustomersPhaseDefinition->getId()]));
        self::assertCount(1, $this->getEntriesWhereInIds(AccessControl::class, [$otherCustomersAccessControlPermission->getId()]));
        self::assertCount(1, $this->getEntriesWhereInIds(UserAccessControl::class, [$otherCustomersUserAccessControl->getId()]));
        self::assertCount(1, $this->getEntries(ReportEntry::class, ['identifier' => $otherCustomersReportEntry->getIdentifier()]));
    }

    private function createAccessControlEntry(Customer $customer): AccessControl
    {
        $accessControlService = $this->getContainer()->get(AccessControlService::class);
        $role = $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0];

        $accessControl = $accessControlService->createPermission(
            'my_permission',
            OrgaFactory::createOne()->_real(),
            $customer,
            $role
        );
        self::assertInstanceOf(AccessControl::class, $accessControl);

        return $accessControl;
    }

    /**
     * All attributes are set explicitly because the UserAccessControlFactory
     * defaults use attribute-closures that this Foundry version does not
     * evaluate, and its UserFactory default creates users without an orga.
     */
    private function createUserAccessControlEntry(Customer $customer): UserAccessControl
    {
        $user = UserFactory::createOne(['orga' => $orga = OrgaFactory::createOne()])->_real();

        return UserAccessControlFactory::createOne([
            'user'         => $user,
            'organisation' => $orga->_real(),
            'customer'     => $customer,
            'role'         => $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0],
        ])->_real();
    }

    private function createReportEntry(Customer $customer): ReportEntry
    {
        $reportEntry = new ReportEntry();
        $reportEntry->setCategory('test');
        $reportEntry->setGroup('test');
        $reportEntry->setUser($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $reportEntry->setIdentifier(bin2hex(random_bytes(16)));
        $reportEntry->setMessage('test');
        $reportEntry->setIncoming('test');
        $reportEntry->setIdentifierType('procedure');
        $reportEntry->setCustomer($customer);

        $reportService = $this->getContainer()->get(ReportService::class);
        $reportService->persistAndFlushReportEntry($reportEntry);

        return $reportEntry;
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
