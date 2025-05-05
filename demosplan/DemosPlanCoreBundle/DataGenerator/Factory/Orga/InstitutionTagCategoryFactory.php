<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
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
    public static function class(): string
    {
        return InstitutionTagCategory::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'creationDate'     => self::faker()->dateTime(),
            'customer'         => CustomerFactory::new(),
            'modificationDate' => self::faker()->dateTime(),
            'name'             => self::faker()->text(255),
        ];
    }

    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(InstitutionTagCategory $institutionTagCategory): void {})
        ;
    }
}
