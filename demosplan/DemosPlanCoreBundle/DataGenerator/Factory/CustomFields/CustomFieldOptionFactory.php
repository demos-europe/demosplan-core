<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use Ramsey\Uuid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<CustomFieldOption>
 */
final class CustomFieldOptionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return CustomFieldOption::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'    => Uuid::uuid4()->toString(),
            'label' => self::faker()->word(),
        ];
    }

    public function withLabel(string $label): self
    {
        return $this->with(['label' => $label]);
    }

    public static function fromLabel(string $label): self
    {
        return self::new()->withLabel($label);
    }
}
