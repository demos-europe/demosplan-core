<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Repository\MunicipalityRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Municipality>
 *
 * @method        Municipality|Proxy                              create(array|callable $attributes = [])
 * @method static Municipality|Proxy                              createOne(array $attributes = [])
 * @method static Municipality|Proxy                              find(object|array|mixed $criteria)
 * @method static Municipality|Proxy                              findOrCreate(array $attributes)
 * @method static Municipality|Proxy                              first(string $sortedField = 'id')
 * @method static Municipality|Proxy                              last(string $sortedField = 'id')
 * @method static Municipality|Proxy                              random(array $attributes = [])
 * @method static Municipality|Proxy                              randomOrCreate(array $attributes = [])
 * @method static MunicipalityRepository|ProxyRepositoryDecorator repository()
 * @method static Municipality[]|Proxy[]                          all()
 * @method static Municipality[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Municipality[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Municipality[]|Proxy[]                          findBy(array $attributes)
 * @method static Municipality[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Municipality[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Municipality&Proxy<Municipality> create(array|callable $attributes = [])
 * @phpstan-method static Municipality&Proxy<Municipality> createOne(array $attributes = [])
 * @phpstan-method static Municipality&Proxy<Municipality> find(object|array|mixed $criteria)
 * @phpstan-method static Municipality&Proxy<Municipality> findOrCreate(array $attributes)
 * @phpstan-method static Municipality&Proxy<Municipality> first(string $sortedField = 'id')
 * @phpstan-method static Municipality&Proxy<Municipality> last(string $sortedField = 'id')
 * @phpstan-method static Municipality&Proxy<Municipality> random(array $attributes = [])
 * @phpstan-method static Municipality&Proxy<Municipality> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<Municipality, EntityRepository> repository()
 * @phpstan-method static list<Municipality&Proxy<Municipality>> all()
 * @phpstan-method static list<Municipality&Proxy<Municipality>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Municipality&Proxy<Municipality>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Municipality&Proxy<Municipality>> findBy(array $attributes)
 * @phpstan-method static list<Municipality&Proxy<Municipality>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Municipality&Proxy<Municipality>> randomSet(int $number, array $attributes = [])
 */
final class MunicipalityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Municipality::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->text(255),
        ];
    }
}
