<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class GenerateCustomerCommandTest extends FunctionalTestCase
{
    public function testSuccessfulExecute(): void
    {
        self::markSkippedForCIIntervention();

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(
            [
                'New Customer',
                'new',
            ]
        );

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        static::assertStringContainsString('Customer successfully created', $output);
    }

    public function testInvalidDuplicateCustomerExecute(): void
    {
        self::markSkippedForCIIntervention();

        $commandTester = $this->getCommandTester();

        // use three inputs, as we want to test whether first input is marked as existing customer
        $commandTester->setInputs(
            [
                LoadCustomerData::BRANDENBURG,
                'new',
                'new',
            ]
        );

        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        // Command warned that customer already exists
        static::assertStringContainsString('This name is already used as a customer', $output);
    }

    public function testWithConfig(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([]);

        $customers = $this->getCustomers('foobar');
        self::assertEmpty($customers);

        $commandTester->execute([], ['config' => 'tests/backend/core/Core/Functional/res/tagFilterNames.yaml']);

        $customers = $this->getCustomers('foobar');
        self::assertNotEmpty($customers);
    }

    /**
     * @return list<Customer>
     */
    private function getCustomers(string $subdomain): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->from(Customer::class, 'customer')
            ->where('subdomain = :subdomain')
            ->setParameter('subdomain', $subdomain)
            ->getQuery()
            ->getResult();
    }

    private function getCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('dplan:data:generate-customer');

        return new CommandTester($command);
    }
}
