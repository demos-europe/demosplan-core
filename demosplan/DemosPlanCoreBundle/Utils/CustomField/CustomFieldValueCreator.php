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

use demosplan\DemosPlanCoreBundle\CustomField\AbstractCustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;

class CustomFieldValueCreator
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly CustomFieldValueFactory $customFieldValueFactory,
    ) {
    }

    public function updateOrAddCustomFieldValues(
        CustomFieldValuesList $currentCustomFieldValuesList,
        array $newCustomFieldValuesData,
        string $sourceEntityId,
        string $sourceEntityClass,
        string $targetEntityClass,
    ): CustomFieldValuesList {
        // Create a completely new object - DON'T modify the passed-in object
        $updatedCustomFieldValuesList = new CustomFieldValuesList();

        // Copy all existing values to the new object first
        if ($currentCustomFieldValuesList->getCustomFieldsValues()) {
            foreach ($currentCustomFieldValuesList->getCustomFieldsValues() as $existingValue) {
                // Clone using factory to ensure proper type
                $clonedValue = $this->customFieldValueFactory->createFromValue($existingValue);
                $updatedCustomFieldValuesList->addCustomFieldValue($clonedValue);
            }
        }

        // Process each new value
        foreach ($newCustomFieldValuesData as $newValueData) {
            // Use factory to create the correct value type
            $newCustomFieldValue = $this->customFieldValueFactory->createFromJson(
                $newValueData,
                $sourceEntityClass,
                $sourceEntityId,
                $targetEntityClass
            );

            // Get field configuration for validation
            $customField = $this->getCustomField(
                $sourceEntityClass,
                $sourceEntityId,
                $targetEntityClass,
                $newCustomFieldValue->getId()
            );

            // Validate the value against business rules
            $this->validateCustomFieldValue($customField, $newCustomFieldValue);

            // Find existing value in our new list
            $existingCustomFieldValue = $updatedCustomFieldValuesList->findById($newCustomFieldValue->getId());

            if ($existingCustomFieldValue instanceof AbstractCustomFieldValue) {
                $this->handleExistingCustomField(
                    $updatedCustomFieldValuesList,
                    $existingCustomFieldValue,
                    $newCustomFieldValue
                );
            } else {
                $this->handleNewCustomField($updatedCustomFieldValuesList, $newCustomFieldValue);
            }
        }

        // Sort fields to ensure consistent ordering
        $updatedCustomFieldValuesList->sortByFieldId();

        // Reindex array
        $updatedCustomFieldValuesList->reindexValues();

        return $updatedCustomFieldValuesList;
    }

    protected function handleExistingCustomField(
        CustomFieldValuesList $updatedCustomFieldValuesList,
        AbstractCustomFieldValue $existingCustomFieldValue,
        AbstractCustomFieldValue $newCustomFieldValue,
    ): void {
        // If the value is null, remove this field from the updated list
        if (null === $newCustomFieldValue->getValue()) {
            $updatedCustomFieldValuesList->removeCustomFieldValue($existingCustomFieldValue);

            return;
        }

        // Update the existing value
        $existingCustomFieldValue->setValue($newCustomFieldValue->getValue());
    }

    protected function handleNewCustomField(
        CustomFieldValuesList $updatedCustomFieldValuesList,
        AbstractCustomFieldValue $newCustomFieldValue,
    ): void {
        // Skip adding fields marked for removal
        if (null !== $newCustomFieldValue->getValue()) {
            $updatedCustomFieldValuesList->addCustomFieldValue($newCustomFieldValue);
        }
    }

    private function getCustomField(string $sourceEntityClass,
        string $sourceEntityId,
        string $targetEntityClass,
        string $customFieldId): CustomFieldInterface
    {
        $customFieldConfigurations = $this->customFieldConfigurationRepository
            ->findCustomFieldConfigurationByCriteria($sourceEntityClass, $sourceEntityId, $targetEntityClass, $customFieldId);

        if (!$customFieldConfigurations) {
            throw new InvalidArgumentException('No custom field configuration found for CustomFieldId.');
        }

        return $customFieldConfigurations[0]->getConfiguration();
    }

    public function getCustomFieldConfigurationByUUID(string $customFieldId): CustomFieldConfiguration
    {
        $customFieldConfigurations = $this->customFieldConfigurationRepository->find($customFieldId);
        if (null === $customFieldConfigurations) {
            throw new InvalidArgumentException('No custom field configuration found for given ID.');
        }

        return $customFieldConfigurations;
    }

    public function getCustomFieldConfigurationById(string $customFieldId): CustomFieldInterface
    {
        $customFieldConfiguration = $this->customFieldConfigurationRepository->find($customFieldId);
        if (null === $customFieldConfiguration) {
            throw new InvalidArgumentException('No custom field configuration found for given ID.');
        }

        return $customFieldConfiguration->getConfiguration();
    }

    private function validateCustomFieldValue(CustomFieldInterface $customField, mixed $value): void
    {
        if (!$customField->isValueValid($value)) {
            throw new InvalidArgumentException(sprintf('Value "%s" is not valid for custom field with ID "%s".', $value, $customField->getId()));
        }
    }
}
