<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Repository\CountyRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<County>
 *
 * @method        County|Proxy                              create(array|callable $attributes = [])
 * @method static County|Proxy                              createOne(array $attributes = [])
 * @method static County|Proxy                              find(object|array|mixed $criteria)
 * @method static County|Proxy                              findOrCreate(array $attributes)
 * @method static County|Proxy                              first(string $sortedField = 'id')
 * @method static County|Proxy                              last(string $sortedField = 'id')
 * @method static County|Proxy                              random(array $attributes = [])
 * @method static County|Proxy                              randomOrCreate(array $attributes = [])
 * @method static CountyRepository|ProxyRepositoryDecorator repository()
 * @method static County[]|Proxy[]                          all()
 * @method static County[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static County[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static County[]|Proxy[]                          findBy(array $attributes)
 * @method static County[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static County[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        County&Proxy<County> create(array|callable $attributes = [])
 * @phpstan-method static County&Proxy<County> createOne(array $attributes = [])
 * @phpstan-method static County&Proxy<County> find(object|array|mixed $criteria)
 * @phpstan-method static County&Proxy<County> findOrCreate(array $attributes)
 * @phpstan-method static County&Proxy<County> first(string $sortedField = 'id')
 * @phpstan-method static County&Proxy<County> last(string $sortedField = 'id')
 * @phpstan-method static County&Proxy<County> random(array $attributes = [])
 * @phpstan-method static County&Proxy<County> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<County, EntityRepository> repository()
 * @phpstan-method static list<County&Proxy<County>> all()
 * @phpstan-method static list<County&Proxy<County>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<County&Proxy<County>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<County&Proxy<County>> findBy(array $attributes)
 * @phpstan-method static list<County&Proxy<County>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<County&Proxy<County>> randomSet(int $number, array $attributes = [])
 */
final class CountyFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return County::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->text(36),
        ];
    }
}
