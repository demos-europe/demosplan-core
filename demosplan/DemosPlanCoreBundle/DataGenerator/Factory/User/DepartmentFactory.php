<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Department>
 *
 * @method        Department|Proxy                              create(array|callable $attributes = [])
 * @method static Department|Proxy                              createOne(array $attributes = [])
 * @method static Department|Proxy                              find(object|array|mixed $criteria)
 * @method static Department|Proxy                              findOrCreate(array $attributes)
 * @method static Department|Proxy                              first(string $sortedField = 'id')
 * @method static Department|Proxy                              last(string $sortedField = 'id')
 * @method static Department|Proxy                              random(array $attributes = [])
 * @method static Department|Proxy                              randomOrCreate(array $attributes = [])
 * @method static DepartmentRepository|ProxyRepositoryDecorator repository()
 * @method static Department[]|Proxy[]                          all()
 * @method static Department[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Department[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Department[]|Proxy[]                          findBy(array $attributes)
 * @method static Department[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Department[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Department&Proxy<Department> create(array|callable $attributes = [])
 * @phpstan-method static Department&Proxy<Department> createOne(array $attributes = [])
 * @phpstan-method static Department&Proxy<Department> find(object|array|mixed $criteria)
 * @phpstan-method static Department&Proxy<Department> findOrCreate(array $attributes)
 * @phpstan-method static Department&Proxy<Department> first(string $sortedField = 'id')
 * @phpstan-method static Department&Proxy<Department> last(string $sortedField = 'id')
 * @phpstan-method static Department&Proxy<Department> random(array $attributes = [])
 * @phpstan-method static Department&Proxy<Department> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<Department, EntityRepository> repository()
 * @phpstan-method static list<Department&Proxy<Department>> all()
 * @phpstan-method static list<Department&Proxy<Department>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Department&Proxy<Department>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Department&Proxy<Department>> findBy(array $attributes)
 * @phpstan-method static list<Department&Proxy<Department>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Department&Proxy<Department>> randomSet(int $number, array $attributes = [])
 */
final class DepartmentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Department::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'createdDate'  => self::faker()->dateTime(),
            'deleted'      => self::faker()->boolean(),
            'modifiedDate' => self::faker()->dateTime(),
            'name'         => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }
}
