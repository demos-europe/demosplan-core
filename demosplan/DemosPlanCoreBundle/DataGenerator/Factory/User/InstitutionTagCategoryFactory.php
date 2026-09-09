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

use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagCategoryRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<InstitutionTagCategory>
 *
 * @method        InstitutionTagCategory|Proxy                              create(array|callable $attributes = [])
 * @method static InstitutionTagCategory|Proxy                              createOne(array $attributes = [])
 * @method static InstitutionTagCategory|Proxy                              find(object|array|mixed $criteria)
 * @method static InstitutionTagCategory|Proxy                              findOrCreate(array $attributes)
 * @method static InstitutionTagCategory|Proxy                              first(string $sortedField = 'id')
 * @method static InstitutionTagCategory|Proxy                              last(string $sortedField = 'id')
 * @method static InstitutionTagCategory|Proxy                              random(array $attributes = [])
 * @method static InstitutionTagCategory|Proxy                              randomOrCreate(array $attributes = [])
 * @method static InstitutionTagCategoryRepository|ProxyRepositoryDecorator repository()
 * @method static InstitutionTagCategory[]|Proxy[]                          all()
 * @method static InstitutionTagCategory[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static InstitutionTagCategory[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static InstitutionTagCategory[]|Proxy[]                          findBy(array $attributes)
 * @method static InstitutionTagCategory[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static InstitutionTagCategory[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class InstitutionTagCategoryFactory extends PersistentProxyObjectFactory
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
            'name'     => self::faker()->unique()->words(3, true),
            'customer' => CustomerFactory::new(),
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
        return InstitutionTagCategory::class;
    }
}
