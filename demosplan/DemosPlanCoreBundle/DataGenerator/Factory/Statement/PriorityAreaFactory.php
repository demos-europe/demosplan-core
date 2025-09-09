<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Repository\PriorityAreaRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<PriorityArea>
 *
 * @method        PriorityArea|Proxy                              create(array|callable $attributes = [])
 * @method static PriorityArea|Proxy                              createOne(array $attributes = [])
 * @method static PriorityArea|Proxy                              find(object|array|mixed $criteria)
 * @method static PriorityArea|Proxy                              findOrCreate(array $attributes)
 * @method static PriorityArea|Proxy                              first(string $sortedField = 'id')
 * @method static PriorityArea|Proxy                              last(string $sortedField = 'id')
 * @method static PriorityArea|Proxy                              random(array $attributes = [])
 * @method static PriorityArea|Proxy                              randomOrCreate(array $attributes = [])
 * @method static PriorityAreaRepository|ProxyRepositoryDecorator repository()
 * @method static PriorityArea[]|Proxy[]                          all()
 * @method static PriorityArea[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static PriorityArea[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static PriorityArea[]|Proxy[]                          findBy(array $attributes)
 * @method static PriorityArea[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static PriorityArea[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        PriorityArea&Proxy<PriorityArea> create(array|callable $attributes = [])
 * @phpstan-method static PriorityArea&Proxy<PriorityArea> createOne(array $attributes = [])
 * @phpstan-method static PriorityArea&Proxy<PriorityArea> find(object|array|mixed $criteria)
 * @phpstan-method static PriorityArea&Proxy<PriorityArea> findOrCreate(array $attributes)
 * @phpstan-method static PriorityArea&Proxy<PriorityArea> first(string $sortedField = 'id')
 * @phpstan-method static PriorityArea&Proxy<PriorityArea> last(string $sortedField = 'id')
 * @phpstan-method static PriorityArea&Proxy<PriorityArea> random(array $attributes = [])
 * @phpstan-method static PriorityArea&Proxy<PriorityArea> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<PriorityArea, EntityRepository> repository()
 * @phpstan-method static list<PriorityArea&Proxy<PriorityArea>> all()
 * @phpstan-method static list<PriorityArea&Proxy<PriorityArea>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<PriorityArea&Proxy<PriorityArea>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<PriorityArea&Proxy<PriorityArea>> findBy(array $attributes)
 * @phpstan-method static list<PriorityArea&Proxy<PriorityArea>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<PriorityArea&Proxy<PriorityArea>> randomSet(int $number, array $attributes = [])
 */
final class PriorityAreaFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PriorityArea::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'key' => self::faker()->text(36),
        ];
    }
}
