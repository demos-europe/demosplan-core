<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<InstitutionTag>
 *
 * @method        InstitutionTag|Proxy                              create(array|callable $attributes = [])
 * @method static InstitutionTag|Proxy                              createOne(array $attributes = [])
 * @method static InstitutionTag|Proxy                              find(object|array|mixed $criteria)
 * @method static InstitutionTag|Proxy                              findOrCreate(array $attributes)
 * @method static InstitutionTag|Proxy                              first(string $sortedField = 'id')
 * @method static InstitutionTag|Proxy                              last(string $sortedField = 'id')
 * @method static InstitutionTag|Proxy                              random(array $attributes = [])
 * @method static InstitutionTag|Proxy                              randomOrCreate(array $attributes = [])
 * @method static InstitutionTagRepository|ProxyRepositoryDecorator repository()
 * @method static InstitutionTag[]|Proxy[]                          all()
 * @method static InstitutionTag[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static InstitutionTag[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static InstitutionTag[]|Proxy[]                          findBy(array $attributes)
 * @method static InstitutionTag[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static InstitutionTag[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class InstitutionTagFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array
    {
        return [
            'label'    => self::faker()->unique()->words(2, true),
            'category' => InstitutionTagCategoryFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return InstitutionTag::class;
    }
}
