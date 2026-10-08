<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldProvider;

/**
 * Checks that selected options belong to the custom fields they are selected for.
 * The field type does not matter, each field answers for its own options.
 */
class CustomFieldOptionSelectionValidator
{
    public function __construct(private readonly CustomFieldProvider $customFieldProvider)
    {
    }

    /**
     * @param array<array-key, list<string>> $selectedOptionIdsByFieldId
     *
     * @throws InvalidArgumentException if an option does not belong to the field it is selected for
     */
    public function validate(array $selectedOptionIdsByFieldId): void
    {
        // PHP turns numeric array keys into integers, but the lookup expects string ids.
        $fieldIds = array_map('strval', array_keys($selectedOptionIdsByFieldId));
        $customFields = $this->customFieldProvider->getCustomFieldsByIds($fieldIds);

        foreach ($selectedOptionIdsByFieldId as $fieldId => $optionIds) {
            $customField = $customFields[$fieldId]
                ?? throw new InvalidArgumentException(sprintf('Unknown custom field "%s".', $fieldId));

            $customField->assertHasOptions($optionIds);
        }
    }
}
