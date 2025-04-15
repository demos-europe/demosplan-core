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
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;

class CustomFieldValueCreator extends CoreService
{
    public function __construct(private readonly CustomFieldFactory $customFieldFactory, private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    public function createCustomFieldValue($fields, $sourceEntityClass, $sourceEntityId, $targetEntityClass, $targetEntityId): CustomFieldValue
    {
        $customFieldConfiguration = $this->getCustomFieldConfiguration(
            $sourceEntityClass,
            $sourceEntityId,
            $targetEntityClass
        );


        $customField = $this->findCustomField($customFieldConfiguration, $fields['id']);

        $this->validateCustomFieldValue($customField, $fields['value']);

        return $this->buildCustomFieldValue($fields);
    }

    private function getCustomFieldConfiguration(
        string $sourceEntityClass,
        string $sourceEntityId,
        string $targetEntityClass
    ): CustomFieldConfiguration {
        $customFieldConfiguration = $this->customFieldConfigurationRepository
            ->findCustomFieldConfigurationByCriteria($sourceEntityClass, $sourceEntityId, $targetEntityClass);

        if (!$customFieldConfiguration) {
            throw new InvalidArgumentException('No custom field configuration found for CustomFieldId.');
        }

        return $customFieldConfiguration;
    }

    private function findCustomField(CustomFieldConfiguration $configuration, string $customFieldId): AbstractCustomField
    {
        foreach ($configuration->getConfiguration()->getCustomFieldsList() as $field) {
            if ($field->getId() === $customFieldId) {
                return $field;
            }
        }

        throw new InvalidArgumentException(sprintf('Custom field with ID "%s" not found.', $customFieldId));
    }

    private function validateCustomFieldValue(AbstractCustomField $customField, mixed $value): void
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
