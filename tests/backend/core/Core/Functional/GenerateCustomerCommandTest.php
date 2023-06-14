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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class GenerateCustomerCommandTest extends FunctionalTestCase
{
    use CommandTesterTrait;

    public function testSuccessfulExecute(): void
    {
        self::markSkippedForCIIntervention();
        $commandTester = $this->getCommandTester();

        $newCustomerName = 'New Customer';
        $commandTester->setInputs(
            [
                $newCustomerName,
                'new',
            ]
        );

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        static::assertStringContainsString("Customer '$newCustomerName' was successfully created", $output);
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

    /**
     * @dataProvider getCustomerTestData()
     */
    public function testWithNameAndSubdomain(string $customerName, string $customerSubdomain): void
    {
        // needs mysql connection somehow
        self::markSkippedForCIIntervention();

        $commandTester = $this->getCommandTester();
        $customers = $this->getCustomers($customerSubdomain);
        self::assertEmpty($customers);

        $commandTester->execute([
            '--name'      => $customerName,
            '--subdomain' => $customerSubdomain,
        ]);

        $customers = $this->getCustomers($customerSubdomain);
        self::assertCount(1, $customers);
    }

    public function getCustomerTestData()
    {
        return [
            ['foobar', 'foobar'],
            ['Foobar', 'foobar'],
        ];
    }

    /**
     * @return list<Customer>
     */
    private function getCustomers(string $subdomain): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('customer')
            ->from(Customer::class, 'customer')
            ->where('customer.subdomain = :subdomain')
            ->setParameter('subdomain', $subdomain)
            ->getQuery()
            ->getResult();
    }

    private function getCommandTester(): CommandTester
    {
        return $this->getCommandTesterByName(self::bootKernel(), 'dplan:data:generate-customer');
    }
}
