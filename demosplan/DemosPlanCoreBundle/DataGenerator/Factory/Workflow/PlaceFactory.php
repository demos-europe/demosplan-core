<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Place>
 *
 * @method        Place|Proxy                              create(array|callable $attributes = [])
 * @method static Place|Proxy                              createOne(array $attributes = [])
 * @method static Place|Proxy                              find(object|array|mixed $criteria)
 * @method static Place|Proxy                              findOrCreate(array $attributes)
 * @method static Place|Proxy                              first(string $sortedField = 'id')
 * @method static Place|Proxy                              last(string $sortedField = 'id')
 * @method static Place|Proxy                              random(array $attributes = [])
 * @method static Place|Proxy                              randomOrCreate(array $attributes = [])
 * @method static PlaceRepository|ProxyRepositoryDecorator repository()
 * @method static Place[]|Proxy[]                          all()
 * @method static Place[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Place[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Place[]|Proxy[]                          findBy(array $attributes)
 * @method static Place[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Place[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Place&Proxy<Place> create(array|callable $attributes = [])
 * @phpstan-method static Place&Proxy<Place> createOne(array $attributes = [])
 * @phpstan-method static Place&Proxy<Place> find(object|array|mixed $criteria)
 * @phpstan-method static Place&Proxy<Place> findOrCreate(array $attributes)
 * @phpstan-method static Place&Proxy<Place> first(string $sortedField = 'id')
 * @phpstan-method static Place&Proxy<Place> last(string $sortedField = 'id')
 * @phpstan-method static Place&Proxy<Place> random(array $attributes = [])
 * @phpstan-method static Place&Proxy<Place> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<Place, EntityRepository> repository()
 * @phpstan-method static list<Place&Proxy<Place>> all()
 * @phpstan-method static list<Place&Proxy<Place>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Place&Proxy<Place>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Place&Proxy<Place>> findBy(array $attributes)
 * @phpstan-method static list<Place&Proxy<Place>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Place&Proxy<Place>> randomSet(int $number, array $attributes = [])
 */
final class PlaceFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Place::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'description' => self::faker()->text(255),
            'name'        => self::faker()->text(255),
            'procedure'   => ProcedureFactory::new(),
            'solved'      => self::faker()->boolean(),
            'sortIndex'   => self::faker()->randomNumber(),
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
