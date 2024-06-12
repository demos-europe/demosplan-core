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

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

use function PHPUnit\Framework\assertSame;

class ContainerInitCommandTest extends FunctionalTestCase
{
    use CommandTesterTrait;

    public function testConfigWithUser(): void
    {
        // test fails when performed multiple times
        self::markSkippedForCIIntervention();

        $this->removeCustomer('foobar');
        $this->getCommandTester()->execute([
            '--customerConfig' => 'tests/backend/core/Core/Functional/res/customerConfig_with_user.yaml',
        ]);
        $customers = $this->getCustomers('foobar');
        $customer = $customers[0];
        $userRoles = $customer->getUserRoles();
        self::assertCount(1, $userRoles);
        /** @var UserRoleInCustomer $userRole */
        $userRole = $userRoles[0];
        self::assertSame(Role::CUSTOMER_MASTER_USER, $userRole->getRole()->getCode());
        $user = $userRole->getUser();
        self::assertSame('bob-sh27@demos-deutschland.de', $user->getLogin());
        assertSame('bob-sh27@demos-deutschland.de', $user->getEmail());
        self::assertNull($user->getOrga());
        self::assertNull($user->getDepartment());
        self::assertSame('', $user->getName());
    }

    public function testConfigWithoutUser(): void
    {
        // test fails when performed multiple times
        // self::markSkippedForCIIntervention();

        $commandTester = $this->getCommandTester();
        $customers = $this->getCustomers('foobar');
        self::assertEmpty($customers);

        $exitCode = $commandTester->execute([
            '--customerConfig'   => 'tests/backend/core/Core/Functional/res/customerConfig_without_user.yaml',
            '--skip-es-populate' => true,
        ]);
        self::assertSame(0, $exitCode);

        $customers = $this->getCustomers('foobar');
        self::assertCount(1, $customers);

        $customer = $customers[0];
        self::assertSame('Foobar', $customer->getName());
        self::assertSame('foobar', $customer->getSubdomain());
        $userRoles = $customer->getUserRoles();
        self::assertCount(0, $userRoles);
    }

    public function testWithMissingConfig(): void
    {
        $this->expectException(InvalidOptionException::class);
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--config' => null]);

        $customers = $this->getCustomers('foobar');
        self::assertCount(0, $customers);
    }

    public function testInvalidConfigValuesWithoutUser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--config' => 'tests/backend/core/Core/Functional/res/customerConfig_without_user_invalid.yaml']);

        $customers = $this->getCustomers('foobar');
        self::assertCount(0, $customers);
    }

    public function testInvalidConfigUserValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--config' => 'tests/backend/core/Core/Functional/res/customerConfig_with_invalid_user.yaml']);

        $customers = $this->getCustomers('foobar');
        self::assertCount(0, $customers);
    }

    /**
     * @dataProvider getInvalidConfigs()
     */
    public function testWithInvalidConfigPath(mixed $config): void
    {
        $this->expectException(InvalidArgumentException::class);
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--config' => $config]);

        $customers = $this->getCustomers('foobar');
        self::assertCount(0, $customers);
    }

    /**
     * @return list<array{0: mixed}>
     */
    public function getInvalidConfigs(): array
    {
        return [
            [1],
            [0],
            [-1],
        ];
    }

    private function getCommandTester(): CommandTester
    {
        return $this->getCommandTesterByName(self::bootKernel(), 'dplan:container:init');
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

    private function removeCustomer(string $subdomain): int
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->delete(Customer::class, 'customer')
            ->where('customer.subdomain = :subdomain')
            ->setParameter('subdomain', $subdomain)
            ->getQuery();

        return $query->execute();
    }
}
