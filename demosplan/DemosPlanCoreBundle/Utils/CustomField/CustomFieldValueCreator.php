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

use demosplan\DemosPlanCoreBundle\CustomField\AbstractCustomField;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;

class CustomFieldValueCreator extends CoreService
{
    public function __construct(private readonly CustomFieldFactory $customFieldFactory, private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    public function updateOrAddCustomFieldValues(
        CustomFieldValuesList $customFieldList,
        array $customFields,
        string $sourceEntityId,
        string $sourceEntityClass,
        string $targetEntityClass
    ): CustomFieldValuesList
    {
        foreach ($customFields as $field) {
            if (isset($field['id'], $field['value'])) {

                $customField = $this->getCustomField(
                    $sourceEntityClass,
                    $sourceEntityId,
                    $targetEntityClass,
                    $field['id']);
                $this->validateCustomFieldValue($customField, $field['value']);


                $existingCustomFieldValue = $this->findCustomFieldValue($customFieldList, $field['id']);

                if ($existingCustomFieldValue) {
                    $existingCustomFieldValue->setValue($field['value']);
                } else {
                    $customFieldValue = $this->buildCustomFieldValue($field);
                    $customFieldList->addCustomFieldValue($customFieldValue);
                }
            }
        }

        return $customFieldList;
    }

    private function findCustomFieldValue(CustomFieldValuesList $customFieldList, string $fieldId): ?CustomFieldValue
    {
        foreach ($customFieldList->getCustomFieldsValues() as $customFieldValue) {
            if ($customFieldValue->getId() === $fieldId) {
                return $customFieldValue;
            }
        }
        return null;
    }


    private function getCustomField(string $sourceEntityClass,
                                    string $sourceEntityId,
                                    string $targetEntityClass,
                                    string $customFieldId): CustomFieldInterface
    {
     $customFieldConfiguration = $this->getCustomFieldConfiguration(
         $sourceEntityClass,
         $sourceEntityId,
         $targetEntityClass,
         $customFieldId
     );

     return $customFieldConfiguration->getConfiguration();
    }

    private function getCustomFieldConfiguration(
        string $sourceEntityClass,
        string $sourceEntityId,
        string $targetEntityClass,
        string $customFieldId
    ): CustomFieldConfiguration {
        $customFieldConfigurations = $this->customFieldConfigurationRepository
            ->findCustomFieldConfigurationByCriteria($sourceEntityClass, $sourceEntityId, $targetEntityClass, $customFieldId);

        if (!$customFieldConfigurations) {
            throw new InvalidArgumentException('No custom field configuration found for CustomFieldId.');
        }

       return $customFieldConfigurations[0];
    }

    private function validateCustomFieldValue(CustomFieldInterface $customField, mixed $value): void
    {
        if (!$customField->isValueValid($value)) {
            throw new InvalidArgumentException(sprintf(
                'Value "%s" is not valid for custom field with ID "%s".',
                $value,
                $customField->getId()
            ));
        }
    }

    private function buildCustomFieldValue(array $fields): CustomFieldValue
    {
        $customFieldValue = new CustomFieldValue();
        $customFieldValue->setId($fields['id']);
        $customFieldValue->setValue($fields['value']);

        return $customFieldValue;
    }
}
