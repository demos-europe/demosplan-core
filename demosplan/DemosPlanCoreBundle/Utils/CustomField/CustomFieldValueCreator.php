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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Contraint\ValidMultiSelectValueConstraint;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator\CustomFieldValueValidationService;

class CustomFieldValueCreator
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly CustomFieldValueValidationService $validationService)
    {
    }

    public function updateOrAddCustomFieldValues(
        CustomFieldValuesList $currentCustomFieldValuesList,
        array $newCustomFieldValuesData,
        string $sourceEntityId,
        string $sourceEntityClass,
        string $targetEntityClass,
    ): CustomFieldValuesList {
        // Store original data as JSON representation before making any changes
        // Create a completely new object - DON'T modify the passed-in object at all
        $updatedCustomFieldValuesList = new CustomFieldValuesList();

        // Copy all existing values to the new object first
        if ($currentCustomFieldValuesList->getCustomFieldsValues()) {
            foreach ($currentCustomFieldValuesList->getCustomFieldsValues() as $existingValue) {
                $newValue = new CustomFieldValue();
                $newValue->fromJson($existingValue->toJson());
                $updatedCustomFieldValuesList->addCustomFieldValue($newValue);
            }
        }

        // Parse the new values
        $newCustomFieldValuesList = new CustomFieldValuesList();
        $newCustomFieldValuesList->fromJson($newCustomFieldValuesData);

        // Now apply changes to our new copy
        foreach ($newCustomFieldValuesList->getCustomFieldsValues() as $newCustomFieldValue) {
            /** @var CustomFieldValue $newCustomFieldValue */
            $customField = $this->getCustomField(
                $sourceEntityClass,
                $sourceEntityId,
                $targetEntityClass,
                $newCustomFieldValue->getId());
            $this->validateCustomFieldValue($customField, $newCustomFieldValue);

            // Find in our new copy, not in the original
            $existingCustomFieldValue = $updatedCustomFieldValuesList->findById($newCustomFieldValue->getId());

            if ($existingCustomFieldValue instanceof CustomFieldValue) {
                $this->handleExistingCustomField($updatedCustomFieldValuesList, $existingCustomFieldValue, $newCustomFieldValue);
            } else {
                $this->handleNewCustomField($updatedCustomFieldValuesList, $newCustomFieldValue);
            }
        }

        // Sort fields to ensure consistent ordering in the database
        $updatedCustomFieldValuesList->sortByFieldId();

        // At the very end, before returning, ensure array is properly indexed
        $updatedCustomFieldValuesList->reindexValues();

        // Never modify the passed-in object - return a completely new one
        return $updatedCustomFieldValuesList;
    }

    protected function handleExistingCustomField(
        CustomFieldValuesList $updatedCustomFieldValuesList,
        CustomFieldValue $existingCustomFieldValue,
        CustomFieldValue $newCustomFieldValue,
    ): void {
        // If the value is null, remove this field from the updated list

        if (null === $newCustomFieldValue->getValue()) {
            $updatedCustomFieldValuesList->removeCustomFieldValue($newCustomFieldValue);

            return;
        }

        $existingCustomFieldValue->setValue($newCustomFieldValue->getValue());
    }

    protected function handleNewCustomField(
        CustomFieldValuesList $updatedCustomFieldValuesList,
        CustomFieldValue $newCustomFieldValue,
    ): void {
        // Skip adding fields marked for removal
        if (null !== $newCustomFieldValue->getValue()) {
            $brandNewValue = new CustomFieldValue();
            $brandNewValue->fromJson($newCustomFieldValue->toJson());
            $updatedCustomFieldValuesList->addCustomFieldValue($brandNewValue);
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

    private function validateCustomFieldValue(CustomFieldInterface $customField, CustomFieldValue $value): void
    {
        // Single responsibility: delegate to validation service
        // No if/else chains, no instanceof checks, fully extensible
        $this->validationService->validate($customField, $value);
    }

}
