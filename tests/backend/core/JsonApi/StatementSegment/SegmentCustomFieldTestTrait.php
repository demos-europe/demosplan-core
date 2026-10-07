<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\StatementSegment;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;

/**
 * Helpers to create SEGMENT custom fields and the values segments hold for them.
 */
trait SegmentCustomFieldTestTrait
{
    /**
     * @param list<string> $optionLabels
     */
    private function createSegmentCustomField(Procedure $procedure, string $name, array $optionLabels, bool $multiSelect = false): CustomFieldConfiguration
    {
        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure)
            ->withRelatedTargetEntity('SEGMENT');

        $factory = $multiSelect
            ? $factory->asMultiSelect($name, options: $optionLabels)
            : $factory->asRadioButton($name, options: $optionLabels);

        return $factory->create()->_real();
    }

    /**
     * @return list<string> the option ids, in the order the options were created
     */
    private function getOptionIds(CustomFieldConfiguration $customField): array
    {
        return array_map(
            static fn ($option): string => $option->getId(),
            $customField->getConfiguration()->getOptions()
        );
    }

    /**
     * @param array<string, string|list<string>> $valuesByFieldId scalar option id for single select, list for multi select
     */
    private function buildCustomFieldValues(array $valuesByFieldId): CustomFieldValuesList
    {
        $list = new CustomFieldValuesList();
        foreach ($valuesByFieldId as $fieldId => $value) {
            $customFieldValue = new CustomFieldValue();
            $customFieldValue->setId($fieldId);
            $customFieldValue->setValue($value);
            $list->addCustomFieldValue($customFieldValue);
        }

        return $list;
    }
}
