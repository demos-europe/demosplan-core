<?php declare(strict_types=1);


namespace Tests\Core\Core\Functional;


use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\CustomerCounty;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Story\FullCustomerStory;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class RemoveCustomerCommandTest extends FunctionalTestCase
{

    use CommandTesterTrait;

    /**
     * Related ReportEntries should be deleted.
     * todo; check procedures. sollten procedures des customers gelöscht werden wenn customer gelöscht wird?
     * todo: orgas löschen? (wenn nur in diesem customer), Verfahren löschen?, ReportEntries löschen?
     * im command prüfen, welche procedures über die orga(kommune!) mit dem customter to delete verbunden sind
     * 1. Verfahren grundsätzlich nicht löschen, ReportEntries "detachen" dazu müssen diese Refactored werden.
     * 2. Erstmal "irgendwie" Reportentries werden glöscht, Verfahren bleiben bestehen
     *
     * 3. Sowohl Verafhren als auch Reportentries löschen. Was ist mit Verfahren die mehreren
     *      Mandanten zugeordnet werden können? fehlermeldung schmeissen
     *
     * Bonus: Was ist eigentlich mir Orgas
     * Verfahren werden gelöscht für diesen fall aber nicht pauschal?! -> ja weil dsgvo
     *
     *
     * wenn orga mehr als einem mandaten zugewiesen ist, wird eine fehlermeldung ausgegebn
     *
     *
     *
     */
    public function testReportsOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
        /** @var Customer[] $customers */
        $customers = $this->getEntries(Customer::class, ['name' => FullCustomerStory::NAME]);
        static::assertNotEmpty($customers);
        static::assertInstanceOf(Customer::class, $customers[0]);
        $testCustomerId = $customers[0]->getId();

        $relatedReports = $this->getEntries(ReportEntry::class, ['customer' => $testCustomerId]);
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
     * Related ReportEntries should be deleted.
     */
    public function testBlueprintOnDeleteCustomer()
    {
        //todo
    }

    /**
     * Related procedures should be deleted.
     */
    public function testProcedureOnDeleteCustomer()
    {
        //todo
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
     * Related orgas should not be deleted.
     */
    public function testOrgasOnDeleteCustomer(): void
    {
        FullCustomerStory::load();
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
