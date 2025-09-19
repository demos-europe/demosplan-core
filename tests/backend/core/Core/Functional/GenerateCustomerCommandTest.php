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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class GenerateCustomerCommandTest extends FunctionalTestCase
{
    use CommandTesterTrait;

    public function testSuccessfulExecute(): void
    {
        $commandTester = $this->getCommandTester();

        $newCustomerName = 'New Customer';
        $commandTester->setInputs(
            [
                $newCustomerName,
                'new',
                // the map inputs are not given - so the default values will be chosen
            ]
        );

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        static::assertStringContainsString("Customer '$newCustomerName' was successfully created", $output);
    }

    public function testCreateWithCustomizedMapParams(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->setInputs([
            'TestName1', // Name
            'testsub',   // Subdomain
            '0',         // custom choice url
            '',          // empty url
            '0',         // custom choice layer
            'TestLayer', // Layer
            '0',         // custom choice attribution
            'TestCopyright', // Copyright
        ]);
        $commandTester->execute([]);
        $customers = $this->getCustomers('testsub');
        self::assertCount(1, $customers);
        $customer = reset($customers);
        self::assertSame('TestName1', $customer->getName());
        self::assertSame('testsub', $customer->getSubdomain());
        self::assertSame('', $customer->getBaseLayerUrl());
        self::assertSame('TestLayer', $customer->getBaseLayerLayers());
        self::assertSame('TestCopyright', $customer->getMapAttribution());

        $output = $commandTester->getDisplay();
        static::assertStringContainsString('Please enter the full name of the customer', $output);
        static::assertStringContainsString('Please enter the Subdomain of the customer', $output);
        static::assertStringContainsString('Please enter the Base Layer URL of the customer', $output);
        static::assertStringContainsString('Please enter the Base Layers of the customer', $output);
        static::assertStringContainsString('Please enter the map attribution of the customer', $output);
        static::assertStringContainsString("Customer 'TestName1' was successfully created", $output);
    }

    public function testCreateWithDefaultMapParamsNoInteractions(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--name'            => 'myName',
            '--subdomain'       => 'mysubdomain',
            '--map-parameters'  => 'default',
        ]);
        $customers = $this->getCustomers('mysubdomain');
        self::assertCount(1, $customers);
        $customer = reset($customers);
        self::assertSame('myName', $customer->getName());
        self::assertSame('mysubdomain', $customer->getSubdomain());
        self::assertSame(CustomerInterface::DEFAULT_BASE_LAYER_URL, $customer->getBaseLayerUrl());
        self::assertSame(CustomerInterface::DEFAULT_BASE_LAYER_LAYERS, $customer->getBaseLayerLayers());
        self::assertSame(CustomerInterface::DEFAULT_MAP_ATTRIBUTION, $customer->getMapAttribution());
        $output = $commandTester->getDisplay();
        self::assertStringNotContainsString('Please enter the full name of the customer', $output);
        self::assertStringNotContainsString('Please enter the Subdomain of the customer', $output);
        self::assertStringNotContainsString('Please enter the Base Layer URL of the customer', $output);
        self::assertStringNotContainsString('Please enter the Base Layers of the customer', $output);
        self::assertStringNotContainsString('Please enter the map attribution of the customer', $output);
        static::assertStringContainsString("Customer 'myName' was successfully created", $output);
    }

    public function testInvalidDuplicateCustomerExecute(): void
    {
        $commandTester = $this->getCommandTester();

        // use three inputs, as we want to test whether first input is marked as existing customer
        $commandTester->setInputs(
            [
                LoadCustomerData::BRANDENBURG, // name is already in use
                'new', // second attempt for name
                'new', // subdomain
                // // the map inputs are not given - so the default values will be chosen
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
        $commandTester = $this->getCommandTester();
        $customers = $this->getCustomers($customerSubdomain);
        self::assertEmpty($customers);

        // the map inputs are not given - so the default values will be chosen
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
