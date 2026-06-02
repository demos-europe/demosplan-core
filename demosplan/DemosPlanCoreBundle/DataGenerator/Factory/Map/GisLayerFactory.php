<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Map;

use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Repository\MapRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<GisLayer>
 *
 * @method        GisLayer|Proxy                         create(array|callable $attributes = [])
 * @method static GisLayer|Proxy                         createOne(array $attributes = [])
 * @method static GisLayer|Proxy                         find(object|array|mixed $criteria)
 * @method static GisLayer|Proxy                         findOrCreate(array $attributes)
 * @method static GisLayer|Proxy                         first(string $sortedField = 'id')
 * @method static GisLayer|Proxy                         last(string $sortedField = 'id')
 * @method static GisLayer|Proxy                         random(array $attributes = [])
 * @method static GisLayer|Proxy                         randomOrCreate(array $attributes = [])
 * @method static MapRepository|ProxyRepositoryDecorator repository()
 * @method static GisLayer[]|Proxy[]                     all()
 * @method static GisLayer[]|Proxy[]                     createMany(int $number, array|callable $attributes = [])
 * @method static GisLayer[]|Proxy[]                     createSequence(iterable|callable $sequence)
 * @method static GisLayer[]|Proxy[]                     findBy(array $attributes)
 * @method static GisLayer[]|Proxy[]                     randomRange(int $min, int $max, array $attributes = [])
 * @method static GisLayer[]|Proxy[]                     randomSet(int $number, array $attributes = [])
 */
final class GisLayerFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name'    => self::faker()->words(3, true),
            'url'     => self::faker()->url(),
            'type'    => 'overlay',
            'layers'  => '0',
            'order'   => self::faker()->numberBetween(0, 100),
            'opacity' => 100,
            'enabled' => true,
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return GisLayer::class;
    }
}
