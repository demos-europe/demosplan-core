<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Boilerplate>
 *
 * @method        Boilerplate|Proxy                              create(array|callable $attributes = [])
 * @method static Boilerplate|Proxy                              createOne(array $attributes = [])
 * @method static Boilerplate|Proxy                              find(object|array|mixed $criteria)
 * @method static Boilerplate|Proxy                              findOrCreate(array $attributes)
 * @method static Boilerplate|Proxy                              first(string $sortedField = 'id')
 * @method static Boilerplate|Proxy                              last(string $sortedField = 'id')
 * @method static Boilerplate|Proxy                              random(array $attributes = [])
 * @method static Boilerplate|Proxy                              randomOrCreate(array $attributes = [])
 * @method static BoilerplateRepository|ProxyRepositoryDecorator repository()
 * @method static Boilerplate[]|Proxy[]                          all()
 * @method static Boilerplate[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Boilerplate[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Boilerplate[]|Proxy[]                          findBy(array $attributes)
 * @method static Boilerplate[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Boilerplate[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class BoilerplateFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Boilerplate::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'procedure' => ProcedureFactory::new(),
            'title'     => self::faker()->words(3, true),
            'text'      => self::faker()->paragraph(),
            'verified'  => false,
        ];
    }
}
