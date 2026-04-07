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
use demosplan\DemosPlanCoreBundle\Command\Data\DetachOrganisationFromCustomerCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class DetachOrganisationFromCustomerCommandTest extends FunctionalTestCase
{
    private const CUSTOMER_NAME = 'Test Customer';
    private const CUSTOMER_SUBDOMAIN = 'test-sub';
    private const CUSTOMER_ID = 'test-id';
    private const CUSTOMER_SELECTION = 'test-sub id: test-id';
    private const ORGA_NAME = 'Test Organisation';
    private const ORGA_ID = 'test-orga-id';
    private const ORGA_TYPE_LABEL = 'Planning Agency';
    private const ORGA_LABEL = 'Test Organisation | Type: Planning Agency | Status: accepted | ID: test-orga-id';

    private ?MockObject $parameterBagMock;
    private ?MockObject $customerRepositoryMock;
    private ?MockObject $entityManagerMock;
    private ?MockObject $questionHelperMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->questionHelperMock = $this->createMock(QuestionHelper::class);
    }

    public function testDryRunMakesNoChanges(): void
    {
        $this->setUpDefaultCustomerWithOrga();

        $this->questionHelperMock->method('ask')
            ->willReturnOnConsecutiveCalls(
                self::CUSTOMER_SELECTION,
                [self::ORGA_LABEL]
            );

        $this->entityManagerMock->expects(self::never())->method('remove');
        $this->entityManagerMock->expects(self::never())->method('flush');

        $commandTester = $this->executeCommand(['--dry-run' => true]);
        $output = $commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('Dry run complete', $output);
        self::assertStringContainsString(self::ORGA_NAME, $output);
    }

    public function testDetachOrganisationWithConfirmation(): void
    {
        [$orgaStatus] = $this->setUpDefaultCustomerWithOrga();

        $this->questionHelperMock->method('ask')
            ->willReturnOnConsecutiveCalls(
                self::CUSTOMER_SELECTION,
                [self::ORGA_LABEL],
                true
            );

        $this->entityManagerMock->expects(self::once())->method('remove')->with($orgaStatus);
        $this->entityManagerMock->expects(self::once())->method('flush');

        $commandTester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('1 organisation entry/entries detached', $commandTester->getDisplay());
    }

    public function testAbortOnDeniedConfirmation(): void
    {
        $this->setUpDefaultCustomerWithOrga();

        $this->questionHelperMock->method('ask')
            ->willReturnOnConsecutiveCalls(
                self::CUSTOMER_SELECTION,
                [self::ORGA_LABEL],
                false
            );

        $this->entityManagerMock->expects(self::never())->method('remove');
        $this->entityManagerMock->expects(self::never())->method('flush');

        $commandTester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('Aborted', $commandTester->getDisplay());
    }

    public function testNoOrganisationsForCustomer(): void
    {
        $customer = $this->createCustomerWithOrgaStatuses('Empty Customer', 'empty-sub', 'empty-id', []);

        $this->customerRepositoryMock->method('findAll')->willReturn([$customer]);
        $this->questionHelperMock->method('ask')->willReturn('empty-sub id: empty-id');

        $commandTester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No detachable organisations available', $commandTester->getDisplay());
    }

    public function testDetachMultipleOrganisations(): void
    {
        $orgaType = $this->createOrgaType(self::ORGA_TYPE_LABEL);
        $orga1 = $this->createOrga('First Org', 'orga-id-1');
        $orga2 = $this->createOrga('Second Org', 'orga-id-2');
        $orgaStatus1 = $this->createOrgaStatusInCustomer($orga1, $orgaType, 'accepted');
        $orgaStatus2 = $this->createOrgaStatusInCustomer($orga2, $orgaType, 'accepted');
        $customer = $this->createCustomerWithOrgaStatuses(self::CUSTOMER_NAME, self::CUSTOMER_SUBDOMAIN, self::CUSTOMER_ID, [$orgaStatus1, $orgaStatus2]);

        $this->customerRepositoryMock->method('findAll')->willReturn([$customer]);

        $label1 = 'First Org | Type: Planning Agency | Status: accepted | ID: orga-id-1';
        $label2 = 'Second Org | Type: Planning Agency | Status: accepted | ID: orga-id-2';
        $this->questionHelperMock->method('ask')
            ->willReturnOnConsecutiveCalls(
                self::CUSTOMER_SELECTION,
                [$label1, $label2],
                true
            );

        $this->entityManagerMock->expects(self::exactly(2))->method('remove');
        $this->entityManagerMock->expects(self::once())->method('flush');

        $commandTester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('2 organisation entry/entries detached', $commandTester->getDisplay());
    }

    public function testCitizenOrganisationNotSelectable(): void
    {
        $orgaType = $this->createOrgaType(self::ORGA_TYPE_LABEL);
        $citizenOrga = $this->createOrga('Privatperson', UserInterface::ANONYMOUS_USER_ORGA_ID);
        $citizenOrga->method('isDefaultCitizenOrganisation')->willReturn(true);
        $regularOrga = $this->createOrga('Regular Org', 'regular-orga-id');
        $regularOrga->method('isDefaultCitizenOrganisation')->willReturn(false);

        $citizenStatus = $this->createOrgaStatusInCustomer($citizenOrga, $orgaType, 'accepted');
        $regularStatus = $this->createOrgaStatusInCustomer($regularOrga, $orgaType, 'accepted');
        $customer = $this->createCustomerWithOrgaStatuses(self::CUSTOMER_NAME, self::CUSTOMER_SUBDOMAIN, self::CUSTOMER_ID, [$citizenStatus, $regularStatus]);

        $this->customerRepositoryMock->method('findAll')->willReturn([$customer]);

        $capturedChoices = [];
        $regularLabel = 'Regular Org | Type: Planning Agency | Status: accepted | ID: regular-orga-id';
        $callCount = 0;
        $this->questionHelperMock->method('ask')
            ->willReturnCallback(function ($askInput, $askOutput, $question) use (&$capturedChoices, &$callCount, $regularLabel) {
                ++$callCount;

                if (1 === $callCount) {
                    return self::CUSTOMER_SELECTION;
                }
                if ($question instanceof ChoiceQuestion) {
                    $capturedChoices = $question->getChoices();

                    return [$regularLabel];
                }

                return true;
            });

        $commandTester = $this->executeCommand();

        foreach ($capturedChoices as $choice) {
            self::assertStringNotContainsString('Privatperson', $choice);
            self::assertStringNotContainsString(UserInterface::ANONYMOUS_USER_ORGA_ID, $choice);
        }

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('1 organisation entry/entries detached', $commandTester->getDisplay());
    }

    /**
     * @return array{OrgaStatusInCustomer&MockObject}
     */
    private function setUpDefaultCustomerWithOrga(): array
    {
        $orgaType = $this->createOrgaType(self::ORGA_TYPE_LABEL);
        $orga = $this->createOrga(self::ORGA_NAME, self::ORGA_ID);
        $orgaStatus = $this->createOrgaStatusInCustomer($orga, $orgaType, 'accepted');
        $customer = $this->createCustomerWithOrgaStatuses(self::CUSTOMER_NAME, self::CUSTOMER_SUBDOMAIN, self::CUSTOMER_ID, [$orgaStatus]);

        $this->customerRepositoryMock->method('findAll')->willReturn([$customer]);

        return [$orgaStatus];
    }

    private function executeCommand(array $additionalParameters = []): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);
        $application->add(
            new DetachOrganisationFromCustomerCommand(
                $this->parameterBagMock,
                $this->customerRepositoryMock,
                $this->entityManagerMock,
                $this->questionHelperMock
            )
        );
        $command = $application->find(DetachOrganisationFromCustomerCommand::getDefaultName());
        $execute = ['command' => $command->getName()];
        foreach ($additionalParameters as $parameter => $value) {
            $execute[$parameter] = $value;
        }
        $commandTester = new CommandTester($command);
        $commandTester->execute($execute);

        return $commandTester;
    }

    private function createOrga(string $name, string $id): MockObject&Orga
    {
        $orga = $this->createMock(Orga::class);
        $orga->method('getName')->willReturn($name);
        $orga->method('getId')->willReturn($id);

        return $orga;
    }

    private function createOrgaType(string $name): MockObject&OrgaType
    {
        $orgaType = $this->createMock(OrgaType::class);
        $orgaType->method('getLabel')->willReturn($name);

        return $orgaType;
    }

    private function createOrgaStatusInCustomer(
        MockObject&Orga $orga,
        MockObject&OrgaType $orgaType,
        string $status,
    ): MockObject&OrgaStatusInCustomer {
        $orgaStatus = $this->createMock(OrgaStatusInCustomer::class);
        $orgaStatus->method('getOrga')->willReturn($orga);
        $orgaStatus->method('getOrgaType')->willReturn($orgaType);
        $orgaStatus->method('getStatus')->willReturn($status);

        return $orgaStatus;
    }

    private function createCustomerWithOrgaStatuses(
        string $name,
        string $subdomain,
        string $id,
        array $orgaStatuses,
    ): MockObject&Customer {
        $customer = $this->createMock(Customer::class);
        $customer->method('getName')->willReturn($name);
        $customer->method('getSubdomain')->willReturn($subdomain);
        $customer->method('getId')->willReturn($id);
        $customer->method('getOrgaStatuses')->willReturn(new ArrayCollection($orgaStatuses));

        return $customer;
    }
}
