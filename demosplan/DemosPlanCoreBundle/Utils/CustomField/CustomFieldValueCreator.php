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

        // Fetch the schema using the customFieldId
        $customFieldConfiguration = $this->customFieldConfigurationRepository->findCustomFieldConfigurationByCriteria($sourceEntityClass, $sourceEntityId, $targetEntityClass);

        if (!$customFieldConfiguration) {
            throw new InvalidArgumentException('No custom field configuration found for CustomFieldId.');
        }

        $customField = null;
        foreach ($customFieldConfiguration->getConfiguration()->getCustomFieldsList() as $field) {
            /** @var AbstractCustomField $customField */
            if ($field->getId() === $fields['id']) {
                $customField = $field;
                break;
            }
        }

        if (!$customField) {
            throw new InvalidArgumentException(sprintf('Custom field with ID "%s" not found.', $fields['id']));
        }

        //Validate that the value is compliant with the values accepted by customField
        if (!$customField->isValueValid($fields['value'])) {
            throw new InvalidArgumentException(sprintf('Value "%s" is not valid for custom field with ID "%s".', $fields['value'], $fields['id']));
        }


        $customFieldValue = new CustomFieldValue();
        //Validate if ID corresponds to customField configuration
        //Validate if value corresponds to customField configuration
        $customFieldValue->setId($fields['id']);
        $customFieldValue->setValue($fields['value']);

        return $customFieldValue;
    }
}
