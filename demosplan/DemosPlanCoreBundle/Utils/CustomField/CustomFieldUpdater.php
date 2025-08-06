<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Ramsey\Uuid\Uuid;

class CustomFieldUpdater
{
    public function __construct(private readonly CustomFieldFactory $customFieldFactory, private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    public function processOptionsUpdate(array $currentOptions, array $newOptions): array
    {
        $currentOptionsById = collect($currentOptions)->keyBy(fn ($option) => $option->getId());

        return collect($newOptions)
            ->map(function (array $newOption) use ($currentOptionsById) {
                $customFieldOption = new CustomFieldOption();
                $customFieldOption->fromJson([
                    'id'    => $newOption['id'] ?? Uuid::uuid4()->toString(),
                    'label' => $newOption['label'] ?? $currentOptionsById->get($newOption['id'] ?? '')?->getLabel() ?? '',
                ]);

                return $customFieldOption;
            })
            ->toArray();
    }

    public function validateOptionsUpdate(array $newOptions): void
    {
        foreach ($newOptions as $option) {
            if (!isset($option['label']) || empty(trim($option['label']))) {
                throw new InvalidArgumentException('All options must have a non-empty label');
            }
        }
    }
}
