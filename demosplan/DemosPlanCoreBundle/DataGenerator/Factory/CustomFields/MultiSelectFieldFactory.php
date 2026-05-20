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
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<MultiSelectField>
 */
final class MultiSelectFieldFactory extends ObjectFactory
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
