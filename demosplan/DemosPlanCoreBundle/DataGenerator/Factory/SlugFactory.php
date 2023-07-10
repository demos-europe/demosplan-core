<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory;

use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Repository\SlugRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Slug>
 *
 * @method        Slug|Proxy                     create(array|callable $attributes = [])
 * @method static Slug|Proxy                     createOne(array $attributes = [])
 * @method static Slug|Proxy                     find(object|array|mixed $criteria)
 * @method static Slug|Proxy                     findOrCreate(array $attributes)
 * @method static Slug|Proxy                     first(string $sortedField = 'id')
 * @method static Slug|Proxy                     last(string $sortedField = 'id')
 * @method static Slug|Proxy                     random(array $attributes = [])
 * @method static Slug|Proxy                     randomOrCreate(array $attributes = [])
 * @method static SlugRepository|RepositoryProxy repository()
 * @method static Slug[]|Proxy[]                 all()
 * @method static Slug[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Slug[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Slug[]|Proxy[]                 findBy(array $attributes)
 * @method static Slug[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Slug[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class SlugFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function getDefaults(): array
    {
        return [
            'name' => self::faker()->streetName(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Slug $slug): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Slug::class;
    }
}
