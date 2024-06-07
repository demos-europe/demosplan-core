<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional\Command;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Permission\EnablePermissionForCustomerOrgaRoleCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class EnablePermissionForCustomerOrgaRoleCommandTest extends FunctionalTestCase
{
    protected CustomerService|Proxy|null $customerService;

    protected OrgaService|Proxy|null $orgaService;

    protected RoleService|Proxy|null $roleService;
    protected AccessControlService|Proxy|null $accessControlService;

    protected RoleHandler|Proxy|null $roleHandler;

    private Orga|Proxy|null $testOrga;

    private Role|Proxy|null $testRole;

    private Customer|Proxy|null $testCustomer;

    private OrgaType|Proxy|null $testOrgaType;

    private OrgaStatusInCustomer|Proxy|null $testOrgaStatusInCustomer;

    public function setUp(): void
    {
        parent::setUp();
        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $this->customerService = $this->getContainer()->get(CustomerService::class);
        $this->orgaService = $this->getContainer()->get(OrgaService::class);
        $this->roleService = $this->getContainer()->get(RoleService::class);
        $this->accessControlService = $this->getContainer()->get(AccessControlService::class);
        $this->testRole = $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0];

        $this->testOrgaType = OrgaTypeFactory::createOne();
        $this->testOrgaType->setName(OrgaTypeInterface::PLANNING_AGENCY);
        $this->testOrgaType->save();

        $this->testOrga = OrgaFactory::createOne();
        $this->testCustomer = CustomerFactory::createOne();

        $this->testOrgaStatusInCustomer = OrgaStatusInCustomerFactory::createOne();

        $this->testOrgaStatusInCustomer->setOrga($this->testOrga->object());
        $this->testOrgaStatusInCustomer->save();

        $this->testOrgaStatusInCustomer->setCustomer($this->testCustomer->object());
        $this->testOrgaStatusInCustomer->save();

        $this->testOrgaStatusInCustomer->setOrgaType($this->testOrgaType->object());
        $this->testOrgaStatusInCustomer->save();

        $this->testOrgaStatusInCustomer->setStatus(OrgaStatusInCustomerInterface::STATUS_ACCEPTED);
        $this->testOrgaStatusInCustomer->save();

        $this->testOrga->addStatusInCustomer($this->testOrgaStatusInCustomer->object());
        $this->testOrga->save();

        $orgaStatusesCollection = new ArrayCollection([$this->testOrgaStatusInCustomer->object()]);
        $this->testCustomer->setOrgaStatuses($orgaStatusesCollection);
        $this->testCustomer->save();

        $this->testOrga->setStatusInCustomers($orgaStatusesCollection);
        $this->testOrga->save();

    }

    public function testExecute(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $application->add(new EnablePermissionForCustomerOrgaRoleCommand(
            $this->createMock(ParameterBagInterface::class),
            $this->customerService,
            $this->roleService,
            $this->accessControlService,
        ));

        $command = $application->find(EnablePermissionForCustomerOrgaRoleCommand::getDefaultName());
        $commandTester = new CommandTester($command);


        // Define the arguments
        $customerId = $this->testCustomer->getId();
        $roleId = $this->testRole->getId();
        $permission = 'CREATE_PROCEDURES_PERMISSION';

        $commandTester->execute([
            'customerId' => $customerId,
            'roleId' => $roleId,
            'permission' => $permission,
            '--dry-run' => true
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('This is a dry run. No changes have been made to the database.', $output);
        $this->assertStringContainsString('Customer '. $this->testCustomer->getId() . ' ' . $this->testCustomer->getName() , $output);
        $this->assertStringContainsString('Role '. $this->testRole->getId() . ' ' . $this->testRole->getName(), $output);

        // Test without dry-run option
        $commandTester->execute([
            'customerId' => $customerId,
            'roleId' => $roleId,
            'permission' => $permission
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Changes have been applied to the database.', $output);
        $this->assertStringContainsString('Customer '. $this->testCustomer->getId() . ' ' . $this->testCustomer->getName() , $output);
        $this->assertStringContainsString('Role '. $this->testRole->getId() . ' ' . $this->testRole->getName(), $output);

        return $commandTester;
    }

}
