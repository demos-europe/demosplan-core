<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Repository\UserRoleInCustomerRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<UserRoleInCustomer>
 *
 * @method        UserRoleInCustomer|Proxy                     create(array|callable $attributes = [])
 * @method static UserRoleInCustomer|Proxy                     createOne(array $attributes = [])
 * @method static UserRoleInCustomer|Proxy                     find(object|array|mixed $criteria)
 * @method static UserRoleInCustomer|Proxy                     findOrCreate(array $attributes)
 * @method static UserRoleInCustomer|Proxy                     first(string $sortedField = 'id')
 * @method static UserRoleInCustomer|Proxy                     last(string $sortedField = 'id')
 * @method static UserRoleInCustomer|Proxy                     random(array $attributes = [])
 * @method static UserRoleInCustomer|Proxy                     randomOrCreate(array $attributes = [])
 * @method static UserRoleInCustomerRepository|RepositoryProxy repository()
 * @method static UserRoleInCustomer[]|Proxy[]                 all()
 * @method static UserRoleInCustomer[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static UserRoleInCustomer[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static UserRoleInCustomer[]|Proxy[]                 findBy(array $attributes)
 * @method static UserRoleInCustomer[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static UserRoleInCustomer[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class UserRoleInCustomerFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'role' => RoleFactory::new(),
            'user' => UserFactory::new(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return UserRoleInCustomer::class;
    }
}
