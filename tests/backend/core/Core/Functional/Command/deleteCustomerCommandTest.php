<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Data\DeleteCustomerCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\Customer\CustomerDeleter;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class deleteCustomerCommandTest extends FunctionalTestCase
{
    private ?MockObject $parameterBagInterfaceMock;
    private ?MockObject $customerRepositoryMock;
    private ?MockObject $customerDeleterMock;
    private ?MockObject $questionHelperMock;
    private ?array $customers;

    private ?array $orphanedOrgaIds;

    public function setUp(): void
    {
        parent::setUp();
        $this->customerDeleterMock = $this->getMock(CustomerDeleter::class);
        $this->parameterBagInterfaceMock = $this->createMock(ParameterBagInterface::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepository::class);
        $this->questionHelperMock = $this->createMock(QuestionHelper::class);
        $this->customers = $this->getEntries(Customer::class);
        $this->orphanedOrgaIds = ['TestID_1', 'TestID_2', 'TestID_3', 'TestID_4', 'TestID_5'];
        $this->customerDeleterMock->method('deleteCustomer')->willReturn($this->orphanedOrgaIds);
    }

    public function testCommandSuccessfull(): void
    {
        $this->parameterBagInterfaceMock->method('get')->willReturn('test');
        $this->customerRepositoryMock->method('findAll')->willReturn($this->customers);
        /** @var Customer $customer */
        $customer = reset($this->customers);
        $this->questionHelperMock->method('ask')->willReturn($customer->getSubdomain().' id: '.$customer->getId());
        $commandTester = $this->executeCommand();
        $output = $commandTester->getDisplay();

        echo $output;
    }

    private function executeCommand(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);
        $application->add(
            new DeleteCustomerCommand(
                $this->parameterBagInterfaceMock,
                $this->customerRepositoryMock,
                $this->customerDeleterMock,
                $this->questionHelperMock
            )
        );

        $command = $application->find(DeleteCustomerCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(), '--without-repopulate' => true, '--dry-run' => true]
        );

        return $commandTester;
    }
}
