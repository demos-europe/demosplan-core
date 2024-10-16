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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class RegisterUserForCustomerCommandTest extends FunctionalTestCase
{
    public function testSuccessfulExecute(): void
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);
        /** @var Customer $newCustomer */
        $newCustomer = $this->fixtures->getReference('testCustomerBrandenburg');

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(
            [
                $user->getLogin(),
                $newCustomer->getSubdomain(),
                RoleInterface::PLANNING_AGENCY_ADMIN.','.RoleInterface::PUBLIC_AGENCY_COORDINATION,
            ]
        );

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        static::assertStringContainsString('User successfully registered for customer', $output);
        static::assertStringNotContainsString(RoleInterface::API_AI_COMMUNICATOR, $output);
    }

    public function testInvalidUserExecute(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(
            [
                'invalid user login',
            ]
        );

        $commandTester->execute([]);
        self::assertEquals(1, $commandTester->getStatusCode());
    }

    public function testInvalidCustomerExecute(): void
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);
        /** @var Customer $newCustomer */
        $newCustomer = $this->fixtures->getReference('testCustomerBrandenburg');
        $commandTester = $this->getCommandTester();

        // use four inputs, as we want to test whether first input is marked as existing customer
        $commandTester->setInputs(
            [
                $user->getLogin(),
                'invalid customer',
                $newCustomer->getSubdomain(),
                RoleInterface::PUBLIC_AGENCY_SUPPORT,
            ]
        );

        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        // Command warned that customer already exists
        static::assertStringContainsString('Value "invalid customer" is invalid', $output);
        self::assertEquals(0, $commandTester->getStatusCode());
    }

    private function getCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('dplan:data:register-user-for-customer');

        return new CommandTester($command);
    }
}
