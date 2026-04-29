<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<RecommendationVersion>
 *
 * @method        RecommendationVersion|Proxy                              create(array|callable $attributes = [])
 * @method static RecommendationVersion|Proxy                              createOne(array $attributes = [])
 * @method static RecommendationVersion|Proxy                              find(object|array|mixed $criteria)
 * @method static RecommendationVersion|Proxy                              findOrCreate(array $attributes)
 * @method static RecommendationVersion|Proxy                              first(string $sortedField = 'id')
 * @method static RecommendationVersion|Proxy                              last(string $sortedField = 'id')
 * @method static RecommendationVersion|Proxy                              random(array $attributes = [])
 * @method static RecommendationVersion|Proxy                              randomOrCreate(array $attributes = [])
 * @method static RecommendationVersionRepository|ProxyRepositoryDecorator repository()
 * @method static RecommendationVersion[]|Proxy[]                          all()
 * @method static RecommendationVersion[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static RecommendationVersion[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static RecommendationVersion[]|Proxy[]                          findBy(array $attributes)
 * @method static RecommendationVersion[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static RecommendationVersion[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class RecommendationVersionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return RecommendationVersion::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'statement'          => SegmentFactory::new(),
            'versionNumber'      => 1,
            'recommendationText' => self::faker()->text(500),
        ];
    }
}
