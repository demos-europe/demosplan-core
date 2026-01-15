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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\MultiSelectField;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

/**
 * Validates values for MultiSelectField.
 */
class MultiSelectFieldValueValidationStrategy implements CustomFieldValueValidationStrategyInterface
{
    public function supports(CustomFieldInterface $field): bool
    {
        return $field instanceof MultiSelectField;
    }

    public function validate(CustomFieldInterface $field, CustomFieldValue $customFieldValue): void
    {
        $this->isValueValid($field, $customFieldValue->getValue());
    }

    public function isValueValid($field, mixed $value): void
    {
        // Null is always valid (no selection)
        if (null === $value) {
            return;
        }

        // MultiSelect must be an array, not a string
        if (!is_array($value)) {
            return;
        }

        // Required fields must have at least one selection
        if ($field->getRequired() && [] === $value) {
            throw new InvalidArgumentException('Required fields must have at least one selection');
        }

        // Validate each value in the array
        foreach ($value as $singleOptionValueId) {
            // Each element must be a string
            if (!is_string($singleOptionValueId)) {
                throw new InvalidArgumentException('Each element must be a string');
            }

            // Each element must be a valid option ID
            if (null === $field->getCustomOptionValueById($singleOptionValueId)) {
                throw new InvalidArgumentException('Each element must be a valid option ID');
            }
        }
    }
}
