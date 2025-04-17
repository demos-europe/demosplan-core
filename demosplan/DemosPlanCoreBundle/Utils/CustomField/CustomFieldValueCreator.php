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
        CustomFieldValuesList $currentCustomFieldValuesList,
        array                 $newCustomFieldValuesData,
        string                $sourceEntityId,
        string                $sourceEntityClass,
        string                $targetEntityClass
    ): CustomFieldValuesList
    {


        $newCustomFieldValuesList = new CustomFieldValuesList();
        $newCustomFieldValuesList->fromJson(['customFields' => $newCustomFieldValuesData],);

        foreach ($newCustomFieldValuesList->getCustomFieldsValues() as $newCustomFieldValue) {
            /** @var CustomFieldValue $newCustomFieldValue */
            $customField = $this->getCustomField(
                $sourceEntityClass,
                $sourceEntityId,
                $targetEntityClass,
                $newCustomFieldValue->getId());
            $this->validateCustomFieldValue($customField, $newCustomFieldValue->getValue());
            $existingCustomFieldValue = $currentCustomFieldValuesList->findById($newCustomFieldValue->getId());

            if ($existingCustomFieldValue) {
                $existingCustomFieldValue->setValue($newCustomFieldValue->getValue());
            } else {
                $currentCustomFieldValuesList->addCustomFieldValue($newCustomFieldValue);
            }
        }

        return $currentCustomFieldValuesList;
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
}
