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
use RuntimeException;
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
    private const ENV = 'test';

    public function setUp(): void
    {
        parent::setUp();
        $this->customerDeleterMock = $this->getMock(CustomerDeleter::class);
        $this->parameterBagInterfaceMock = $this->createMock(ParameterBagInterface::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepository::class);
        $this->questionHelperMock = $this->createMock(QuestionHelper::class);
        $this->customers = $this->getEntries(Customer::class);
        $this->orphanedOrgaIds = ['TestID_1', 'TestID_2', 'TestID_3', 'TestID_4', 'TestID_5'];
    }

    public function testCommand(): void
    {
        $this->customerDeleterMock->method('deleteCustomer')->willReturn($this->orphanedOrgaIds);
        $this->parameterBagInterfaceMock->method('get')->willReturn(self::ENV);
        $this->customerRepositoryMock->method('findAll')->willReturn($this->customers);
        /** @var Customer $customer */
        $customer = reset($this->customers);
        $this->questionHelperMock->method('ask')->willReturn($customer->getSubdomain().' id: '.$customer->getId());
        $commandTester = $this->executeCommand(
            [DeleteCustomerCommand::OPTION_WITHOUT_ES_REPOPULATE => true, DeleteCustomerCommand::OPTION_DRY_RUN => true]
        );
        $output = $commandTester->getDisplay();

        self::assertStringContainsString('dplan:organisation:delete '.implode(',', $this->orphanedOrgaIds), $output);
        self::assertStringContainsString('run with option dry-run', $output);
        self::assertStringContainsString('run without-es-repopulate', $output);
    }

    public function testDifferentParams(): void
    {
        $this->customerDeleterMock->method('deleteCustomer')->willReturn([]);
        $this->parameterBagInterfaceMock->method('get')->willReturn(self::ENV);
        $this->customerRepositoryMock->method('findAll')->willReturn($this->customers);
        /** @var Customer $customer */
        $customer = reset($this->customers);
        $this->questionHelperMock->method('ask')->willReturn($customer->getSubdomain().' id: '.$customer->getId());

        $output = $this->executeCommand(
            [DeleteCustomerCommand::OPTION_WITHOUT_ES_REPOPULATE => true]
        )->getDisplay();

        self::assertStringNotContainsString('dplan:organisation:delete '.implode(',', $this->orphanedOrgaIds), $output);
        self::assertStringNotContainsString('run with option dry-run', $output);
        self::assertStringContainsString('run without-es-repopulate', $output);

        $output = $this->executeCommand(
            [DeleteCustomerCommand::OPTION_DRY_RUN => true]
        )->getDisplay();
        self::assertStringContainsString('run with option dry-run', $output);
        self::assertStringNotContainsString('run without-es-repopulate', $output);

        $output = $this->executeCommand(
            [DeleteCustomerCommand::OPTION_DRY_RUN => null]
        )->getDisplay();
        self::assertStringContainsString('run with option dry-run', $output);
        self::assertStringNotContainsString('run without-es-repopulate', $output);

        $output = $this->executeCommand(
            []
        )->getDisplay();
        self::assertStringContainsString('Repopulating ES with env: '.self::ENV, $output);

        $output = $this->executeCommand(
            [DeleteCustomerCommand::OPTION_WITHOUT_ES_REPOPULATE => null, DeleteCustomerCommand::OPTION_DRY_RUN => '']
        )->getDisplay();
        self::assertStringContainsString('run with option dry-run', $output);
        self::assertStringContainsString('run without-es-repopulate', $output);
    }

    public function testInvalidCustomerId(): void
    {
        $this->parameterBagInterfaceMock->method('get')->willReturn(self::ENV);
        $this->customerRepositoryMock->method('findAll')->willReturn($this->customers);
        /** @var Customer $customer */
        $customer = reset($this->customers);
        $this->questionHelperMock->method('ask')->willReturn($customer->getSubdomain().' id: NOT_FOUND');

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Given customer is not available.');

        $this->executeCommand(
            [DeleteCustomerCommand::OPTION_WITHOUT_ES_REPOPULATE => true, DeleteCustomerCommand::OPTION_DRY_RUN => true]
        );
    }

    private function executeCommand(array $additionalParameters): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel);
        $application->add(
            new DeleteCustomerCommand(
                $this->parameterBagInterfaceMock,
                $this->customerRepositoryMock,
                $this->customerDeleterMock,
                $this->questionHelperMock
            )
        );
        $command = $application->find(DeleteCustomerCommand::getDefaultName());
        $execute = ['command' => $command->getName()];
        foreach ($additionalParameters as $parameter => $value) {
            $execute['--'.$parameter] = $value;
        }
        $commandTester = new CommandTester($command);
        $commandTester->execute($execute);

        return $commandTester;
    }
}
