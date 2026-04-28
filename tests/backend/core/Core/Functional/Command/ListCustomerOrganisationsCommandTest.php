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
use demosplan\DemosPlanCoreBundle\Command\Data\ListCustomerOrganisationsCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class ListCustomerOrganisationsCommandTest extends FunctionalTestCase
{
    use CustomerCommandTestHelper;

    private const ORGA_TYPE_LABEL = 'Planning Agency';

    private ?MockObject $parameterBagMock;
    private ?MockObject $customerRepositoryMock;
    private ?MockObject $questionHelperMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepository::class);
        $this->questionHelperMock = $this->createMock(QuestionHelper::class);
    }

    public function testListsOrganisationsForCustomer(): void
    {
        $orgaType = $this->createOrgaType(self::ORGA_TYPE_LABEL);
        $orga = $this->createOrga('Test Organisation', 'test-orga-id');
        $customer = $this->createCustomerWithOrgaStatuses('Test Customer', 'test-subdomain', 'test-customer-id', [
            $this->createOrgaStatusInCustomer($orga, $orgaType, 'accepted'),
        ]);

        $this->customerRepositoryMock->method('findAll')->willReturn([$customer]);
        $this->questionHelperMock->method('ask')->willReturn('test-subdomain id: test-customer-id');

        $commandTester = $this->executeCommand();
        $output = $commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('Test Organisation', $output);
        self::assertStringContainsString('test-orga-id', $output);
        self::assertStringContainsString(self::ORGA_TYPE_LABEL, $output);
        self::assertStringContainsString('accepted', $output);
        self::assertStringContainsString('1 organisation(s) found', $output);
    }

    public function testEmptyOrganisationList(): void
    {
        $customer = $this->createCustomerWithOrgaStatuses('Empty Customer', 'empty-subdomain', 'empty-customer-id', []);

        $this->customerRepositoryMock->method('findAll')->willReturn([$customer]);
        $this->questionHelperMock->method('ask')->willReturn('empty-subdomain id: empty-customer-id');

        $commandTester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No organisations found', $commandTester->getDisplay());
    }

    public function testInvalidCustomerSelection(): void
    {
        $customers = $this->getEntries(Customer::class);
        $this->customerRepositoryMock->method('findAll')->willReturn($customers);

        $customer = reset($customers);
        $this->questionHelperMock->method('ask')->willReturn($customer->getSubdomain().' id: NOT_FOUND');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Given customer is not available.');

        $this->executeCommand();
    }

    public function testMultipleOrganisations(): void
    {
        $orgaType1 = $this->createOrgaType(self::ORGA_TYPE_LABEL);
        $orgaType2 = $this->createOrgaType('Municipality');
        $orga1 = $this->createOrga('First Org', 'orga-id-1');
        $orga2 = $this->createOrga('Second Org', 'orga-id-2');

        $customer = $this->createCustomerWithOrgaStatuses('Multi Customer', 'multi-sub', 'multi-id', [
            $this->createOrgaStatusInCustomer($orga1, $orgaType1, 'accepted'),
            $this->createOrgaStatusInCustomer($orga2, $orgaType2, 'pending'),
        ]);

        $this->customerRepositoryMock->method('findAll')->willReturn([$customer]);
        $this->questionHelperMock->method('ask')->willReturn('multi-sub id: multi-id');

        $commandTester = $this->executeCommand();
        $output = $commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('First Org', $output);
        self::assertStringContainsString('Second Org', $output);
        self::assertStringContainsString('2 organisation(s) found', $output);
    }

    private function executeCommand(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);
        $application->add(
            new ListCustomerOrganisationsCommand(
                $this->parameterBagMock,
                $this->customerRepositoryMock,
                $this->questionHelperMock
            )
        );
        $command = $application->find(ListCustomerOrganisationsCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        return $commandTester;
    }
}
