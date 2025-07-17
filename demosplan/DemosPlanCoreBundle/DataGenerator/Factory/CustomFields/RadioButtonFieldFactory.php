<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields;

use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<RadioButtonField>
 *
 * @method        RadioButtonField|Proxy                    create(array|callable $attributes = [])
 * @method static RadioButtonField|Proxy                    createOne(array $attributes = [])
 * @method static RadioButtonField|Proxy                    find(object|array|mixed $criteria)
 * @method static RadioButtonField|Proxy                    findOrCreate(array $attributes)
 * @method static RadioButtonField|Proxy                    first(string $sortedField = 'id')
 * @method static RadioButtonField|Proxy                    last(string $sortedField = 'id')
 * @method static RadioButtonField|Proxy                    random(array $attributes = [])
 * @method static RadioButtonField|Proxy                    randomOrCreate(array $attributes = [])
 * @method static RadioButtonField|ProxyRepositoryDecorator repository()
 * @method static RadioButtonField[]|Proxy[]                all()
 * @method static RadioButtonField[]|Proxy[]                createMany(int $number, array|callable $attributes = [])
 * @method static RadioButtonField[]|Proxy[]                createSequence(iterable|callable $sequence)
 * @method static RadioButtonField[]|Proxy[]                findBy(array $attributes)
 * @method static RadioButtonField[]|Proxy[]                randomRange(int $min, int $max, array $attributes = [])
 * @method static RadioButtonField[]|Proxy[]                randomSet(int $number, array $attributes = [])
 */
final class RadioButtonFieldFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return RadioButtonField::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name'              => 'Color',
            'description'       => 'Select a Color',
            'fieldType'         => 'singleSelect',
            'options'           => self::faker()->rgbColorAsArray(),
            // ['blue', 'red', 'green', 'yellow', 'black', 'white', 'purple', 'orange'],
        ];
    }
}
