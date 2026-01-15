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
use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

/**
 * Validates values for MultiSelectField.
 */
class SingleSelectFieldValueValidationStrategy implements CustomFieldValueValidationStrategyInterface
{
    public function supports(CustomFieldInterface $field): bool
    {
        return $field instanceof RadioButtonField;
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

        // SingleSelect must be a string, not an array
        if (!is_string($value)) {
            throw new InvalidArgumentException('SingleSelect must be a string, not an array');
        }

        $isValidOption = collect($field->getOptions())->contains(fn ($option) => $option->getId() === $value);

        if (!$isValidOption) {
            throw new InvalidArgumentException('SingleSelect invalid option');
        }
    }
}
