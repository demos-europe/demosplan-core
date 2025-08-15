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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Ramsey\Uuid\Uuid;

class CustomFieldUpdater
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    public function updateCustomField(string $entityId, array $attributes): CustomFieldInterface
    {
        // Get the CustomFieldConfiguration from database
        /** @var CustomFieldConfiguration $customFieldConfiguration */
        $customFieldConfiguration = $this->customFieldConfigurationRepository->find($entityId);

        if (!$customFieldConfiguration) {
            throw new InvalidArgumentException("CustomFieldConfiguration with ID '{$entityId}' not found");
        }

        // Get the current CustomField object
        $customField = clone $customFieldConfiguration->getConfiguration();
        $customField->setId($customFieldConfiguration->getId());

        $this->updateBasicFields($customField, $attributes);
        $this->updateOptionsIfPresent($customField, $attributes);
        // Save back to CustomFieldConfiguration
        $customFieldConfiguration->setConfiguration($customField);
        $this->customFieldConfigurationRepository->updateObject($customFieldConfiguration);

        return $customField;
    }

    private function updateBasicFields(CustomFieldInterface $customField, array $attributes): void
    {
        if (isset($attributes['name'])) {
            $customField->setName($attributes['name']);
        }

        if (isset($attributes['description'])) {
            $customField->setDescription($attributes['description']);
        }
    }

    private function updateOptionsIfPresent(CustomFieldInterface $customField, array $attributes): void
    {
        if (!isset($attributes['options'])) {
            return;
        }

        $newOptions = $attributes['options'];
        $currentOptions = $customField->getOptions();

        $updatedOptions = $this->processOptionsUpdate($currentOptions, $newOptions);
        $customField->validate($updatedOptions);
        $customField->setOptions($updatedOptions);
    }

    /**
     * @param CustomFieldOption[] $currentOptions
     * @param CustomFieldOption[] $newOptions
     *
     * @return CustomFieldOption[]
     */
    private function processOptionsUpdate(array $currentOptions, array $newOptions): array
    {
        $currentOptionsById = collect($currentOptions)->keyBy(fn (CustomFieldOption $option) => $option->getId());

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
}
