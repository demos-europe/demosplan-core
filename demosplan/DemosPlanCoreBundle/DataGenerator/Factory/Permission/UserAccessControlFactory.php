<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Permission;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Repository\UserAccessControlRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<UserAccessControl>
 *
 * @method        UserAccessControl|Proxy                              create(array|callable $attributes = [])
 * @method static UserAccessControl|Proxy                              createOne(array $attributes = [])
 * @method static UserAccessControl|Proxy                              find(object|array|mixed $criteria)
 * @method static UserAccessControl|Proxy                              findOrCreate(array $attributes)
 * @method static UserAccessControl|Proxy                              first(string $sortedField = 'id')
 * @method static UserAccessControl|Proxy                              last(string $sortedField = 'id')
 * @method static UserAccessControl|Proxy                              random(array $attributes = [])
 * @method static UserAccessControl|Proxy                              randomOrCreate(array $attributes = [])
 * @method static UserAccessControlRepository|ProxyRepositoryDecorator repository()
 * @method static UserAccessControl[]|Proxy[]                          all()
 * @method static UserAccessControl[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static UserAccessControl[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static UserAccessControl[]|Proxy[]                          findBy(array $attributes)
 * @method static UserAccessControl[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static UserAccessControl[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        UserAccessControl&Proxy<UserAccessControl> create(array|callable $attributes = [])
 * @phpstan-method static UserAccessControl&Proxy<UserAccessControl> createOne(array $attributes = [])
 * @phpstan-method static UserAccessControl&Proxy<UserAccessControl> find(object|array|mixed $criteria)
 * @phpstan-method static UserAccessControl&Proxy<UserAccessControl> findOrCreate(array $attributes)
 * @phpstan-method static UserAccessControl&Proxy<UserAccessControl> first(string $sortedField = 'id')
 * @phpstan-method static UserAccessControl&Proxy<UserAccessControl> last(string $sortedField = 'id')
 * @phpstan-method static UserAccessControl&Proxy<UserAccessControl> random(array $attributes = [])
 * @phpstan-method static UserAccessControl&Proxy<UserAccessControl> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<UserAccessControl, EntityRepository> repository()
 * @phpstan-method static list<UserAccessControl&Proxy<UserAccessControl>> all()
 * @phpstan-method static list<UserAccessControl&Proxy<UserAccessControl>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<UserAccessControl&Proxy<UserAccessControl>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<UserAccessControl&Proxy<UserAccessControl>> findBy(array $attributes)
 * @phpstan-method static list<UserAccessControl&Proxy<UserAccessControl>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<UserAccessControl&Proxy<UserAccessControl>> randomSet(int $number, array $attributes = [])
 */
final class UserAccessControlFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserAccessControl::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'user'         => UserFactory::new(),
            'organisation' => fn (array $attributes) => $attributes['user']->getOrganisation(),
            'customer'     => fn (array $attributes) => $attributes['organisation']->getMainCustomer(),
            'role'         => fn (array $attributes) => $attributes['user']->getDplanRoles()->first(),
            'permission'   => self::faker()->randomElement([
                'feature_statement_bulk_edit',
                'feature_procedure_planning_area_match',
                'area_admin_assessmenttable',
                'area_admin_procedures',
                'area_admin_statement_list',
            ]),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }

    public function withPermission(string $permission): self
    {
        return $this->with(['permission' => $permission]);
    }

    public function forUser($user): self
    {
        return $this->with([
            'user'         => $user,
            'organisation' => fn () => $user->getOrganisation(),
            'customer'     => fn () => $user->getOrganisation()->getCustomer(),
        ]);
    }

    public function withRole($role): self
    {
        return $this->with(['role' => $role]);
    }
}
