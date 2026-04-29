<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields;

use demosplan\DemosPlanCoreBundle\CustomField\TextField;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<TextField>
 */
final class TextFieldFactory extends ObjectFactory
{
    public static function class(): string
    {
        return TextField::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name'        => 'Notes',
            'description' => 'Enter free text',
            'fieldType'   => 'text',
            'required'    => false,
        ];
    }
}
