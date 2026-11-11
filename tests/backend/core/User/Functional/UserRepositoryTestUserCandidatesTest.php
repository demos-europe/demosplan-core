<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Tests\Base\FunctionalTestCase;

class UserRepositoryTestUserCandidatesTest extends FunctionalTestCase
{
    protected ?UserRepository $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(UserRepository::class);
    }

    public function testCandidatesAreScopedToCustomerAndHavePassword(): void
    {
        $customer = self::getContainer()->get(CustomerService::class)->getCurrentCustomer();

        $users = $this->sut->findActiveUsersWithPasswordInCustomer($customer);

        self::assertNotEmpty($users);
        foreach ($users as $user) {
            self::assertInstanceOf(User::class, $user);
            self::assertFalse($user->isDeleted());
            self::assertNotSame('', (string) $user->getPassword());
            self::assertNotSame(User::ANONYMOUS_USER_ID, $user->getId());
            self::assertTrue($user->getOrga()->getCustomers()->contains($customer));
        }
        self::assertSame(count($users), count(array_unique(array_map(static fn (User $u) => $u->getId(), $users))));
    }

    public function testCandidatesExcludeUsersWithoutPassword(): void
    {
        $customer = self::getContainer()->get(CustomerService::class)->getCurrentCustomer();
        $before = $this->sut->findActiveUsersWithPasswordInCustomer($customer);
        self::assertNotEmpty($before);

        $victim = $before[0];
        $victim->setPassword('');
        $this->getEntityManager()->flush();

        $ids = array_map(static fn (User $u) => $u->getId(), $this->sut->findActiveUsersWithPasswordInCustomer($customer));
        self::assertNotContains($victim->getId(), $ids);
    }

    public function testGetTestUsersMatchesOnlyDefaultPassword(): void
    {
        $result = self::getContainer()->get(UserService::class)->getTestUsers('definitely-not-a-fixture-password');

        self::assertCount(0, $result);
    }
}
