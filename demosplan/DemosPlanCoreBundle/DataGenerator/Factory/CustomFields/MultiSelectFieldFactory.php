<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields;

use demosplan\DemosPlanCoreBundle\CustomField\MultiSelectField;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<MultiSelectField>
 *
 * @method        MultiSelectField|Proxy                    create(array|callable $attributes = [])
 * @method static MultiSelectField|Proxy                    createOne(array $attributes = [])
 * @method static MultiSelectField|Proxy                    find(object|array|mixed $criteria)
 * @method static MultiSelectField|Proxy                    findOrCreate(array $attributes)
 * @method static MultiSelectField|Proxy                    first(string $sortedField = 'id')
 * @method static MultiSelectField|Proxy                    last(string $sortedField = 'id')
 * @method static MultiSelectField|Proxy                    random(array $attributes = [])
 * @method static MultiSelectField|Proxy                    randomOrCreate(array $attributes = [])
 * @method static MultiSelectField|ProxyRepositoryDecorator repository()
 * @method static MultiSelectField[]|Proxy[]                all()
 * @method static MultiSelectField[]|Proxy[]                createMany(int $number, array|callable $attributes = [])
 * @method static MultiSelectField[]|Proxy[]                createSequence(iterable|callable $sequence)
 * @method static MultiSelectField[]|Proxy[]                findBy(array $attributes)
 * @method static MultiSelectField[]|Proxy[]                randomRange(int $min, int $max, array $attributes = [])
 * @method static MultiSelectField[]|Proxy[]                randomSet(int $number, array $attributes = [])
 */
final class MultiSelectFieldFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return MultiSelectField::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name'              => 'Categories',
            'description'       => 'Select applicable categories',
            'fieldType'         => 'multiSelect',
            'required'          => false,
            'options'           => self::faker()->randomElements(
                ['Environment', 'Traffic', 'Housing', 'Economy', 'Culture', 'Health'],
                3
            ),
        ];
    }
}
