<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementGroup;
use demosplan\DemosPlanCoreBundle\Repository\StatementGroupRepository;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends StatementFactory<StatementGroup>
 *
 * @method        StatementGroup|Proxy                              create(array|callable $attributes = [])
 * @method static StatementGroup|Proxy                              createOne(array $attributes = [])
 * @method static StatementGroup|Proxy                              find(object|array|mixed $criteria)
 * @method static StatementGroup|Proxy                              findOrCreate(array $attributes)
 * @method static StatementGroup|Proxy                              first(string $sortedField = 'id')
 * @method static StatementGroup|Proxy                              last(string $sortedField = 'id')
 * @method static StatementGroup|Proxy                              random(array $attributes = [])
 * @method static StatementGroup|Proxy                              randomOrCreate(array $attributes = [])
 * @method static StatementGroupRepository|ProxyRepositoryDecorator repository()
 * @method static StatementGroup[]|Proxy[]                          all()
 * @method static StatementGroup[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static StatementGroup[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static StatementGroup[]|Proxy[]                          findBy(array $attributes)
 * @method static StatementGroup[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static StatementGroup[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class StatementGroupFactory extends StatementFactory
{
    public static function class(): string
    {
        return StatementGroup::class;
    }
}
