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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class RegisterOrgaForCustomerCommandTest extends FunctionalTestCase
{
    public function testSuccessfulExecute(): void
    {
        // Arrange
        $sourceCustomer = CustomerFactory::createOne(['subdomain' => 'source-'.uniqid()]);
        $targetCustomer = CustomerFactory::createOne(['subdomain' => 'target-'.uniqid()]);
        $orgaType = OrgaTypeFactory::createOne();
        $orga = OrgaFactory::createOne();
        // Persists the orga→source-customer registration row; not assigned because the command
        // reads its state back from the DB after a fresh kernel boot.
        OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $sourceCustomer,
            'orgaType' => $orgaType,
        ]);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $sourceCustomer->getSubdomain(),
            $targetCustomer->getSubdomain(),
            $orga->getId(),
            'y',
        ]);

        // Act
        $tester->execute([]);

        // Assert
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        self::assertStringContainsString('Orga successfully registered', $output);
        self::assertStringContainsString($targetCustomer->getSubdomain(), $output);
    }

    public function testInvalidOrgaIdExecute(): void
    {
        // Arrange
        $sourceCustomer = CustomerFactory::createOne(['subdomain' => 'source-'.uniqid()]);
        $targetCustomer = CustomerFactory::createOne(['subdomain' => 'target-'.uniqid()]);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $sourceCustomer->getSubdomain(),
            $targetCustomer->getSubdomain(),
            '00000000-0000-0000-0000-000000000000',
        ]);

        // Act
        $tester->execute([]);

        // Assert
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('No Orga found for the given ID', $tester->getDisplay());
    }

    public function testSourceEqualsTargetExecute(): void
    {
        // Arrange
        $customer = CustomerFactory::createOne(['subdomain' => 'same-'.uniqid()]);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $customer->getSubdomain(),
            $customer->getSubdomain(),
        ]);

        // Act
        $tester->execute([]);

        // Assert
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Source and target customer must differ', $tester->getDisplay());
    }

    public function testOrgaNotInSourceExecute(): void
    {
        // Arrange — orga is registered in some unrelated customer, never in `sourceCustomer`
        $unrelatedCustomer = CustomerFactory::createOne(['subdomain' => 'unrelated-'.uniqid()]);
        $sourceCustomer = CustomerFactory::createOne(['subdomain' => 'source-'.uniqid()]);
        $targetCustomer = CustomerFactory::createOne(['subdomain' => 'target-'.uniqid()]);
        $orgaType = OrgaTypeFactory::createOne();
        $orga = OrgaFactory::createOne();
        OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $unrelatedCustomer,
            'orgaType' => $orgaType,
        ]);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $sourceCustomer->getSubdomain(),
            $targetCustomer->getSubdomain(),
            $orga->getId(),
        ]);

        // Act
        $tester->execute([]);

        // Assert
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('not registered in source customer', $tester->getDisplay());
    }

    public function testOrgaAlreadyInTargetExecute(): void
    {
        // Arrange — orga is already registered in both source and target
        $sourceCustomer = CustomerFactory::createOne(['subdomain' => 'source-'.uniqid()]);
        $targetCustomer = CustomerFactory::createOne(['subdomain' => 'target-'.uniqid()]);
        $orgaType = OrgaTypeFactory::createOne();
        $orga = OrgaFactory::createOne();
        OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $sourceCustomer,
            'orgaType' => $orgaType,
        ]);
        OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $targetCustomer,
            'orgaType' => $orgaType,
        ]);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $sourceCustomer->getSubdomain(),
            $targetCustomer->getSubdomain(),
            $orga->getId(),
        ]);

        // Act
        $tester->execute([]);

        // Assert
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('already registered in target customer', $tester->getDisplay());
    }

    private function getCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('dplan:data:register-orga-for-customer');

        return new CommandTester($command);
    }
}
