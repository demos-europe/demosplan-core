<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Segment>
 *
 * @method        Segment|Proxy                              create(array|callable $attributes = [])
 * @method static Segment|Proxy                              createOne(array $attributes = [])
 * @method static Segment|Proxy                              find(object|array|mixed $criteria)
 * @method static Segment|Proxy                              findOrCreate(array $attributes)
 * @method static Segment|Proxy                              first(string $sortedField = 'id')
 * @method static Segment|Proxy                              last(string $sortedField = 'id')
 * @method static Segment|Proxy                              random(array $attributes = [])
 * @method static Segment|Proxy                              randomOrCreate(array $attributes = [])
 * @method static SegmentRepository|ProxyRepositoryDecorator repository()
 * @method static Segment[]|Proxy[]                          all()
 * @method static Segment[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Segment[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Segment[]|Proxy[]                          findBy(array $attributes)
 * @method static Segment[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Segment[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class SegmentFactory extends StatementFactory
{
    protected function defaults(): array|callable
    {
        $defaults = parent::defaults();

        $defaults['orderInProcedure'] = self::faker()->numberBetween(1, 9999);

        return $defaults;
    }

    public static function class(): string
    {
        return Segment::class;
    }
}
