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
use Webmozart\Assert\Assert;

/**
 * Validates values for SingleSelect.
 */
class SingleSelectFieldValueValidationStrategy implements CustomFieldValueValidationStrategyInterface
{
    public function supports(CustomFieldInterface $field): bool
    {
        return $field instanceof RadioButtonField;
    }

    public function validate(CustomFieldInterface $field, CustomFieldValue $customFieldValue): void
    {
        Assert::isInstanceOf($field, RadioButtonField::class);

        // Null is always valid (no selection)
        if (null === $customFieldValue->getValue()) {
            return;
        }

        // SingleSelect must be a string
        if (!is_string($customFieldValue->getValue())) {
            throw new InvalidArgumentException(sprintf('SingleSelect must be a string for CustomFieldId "%s"', $field->getId()));
        }

        $isValidOption = collect($field->getOptions())->contains(fn ($option) => $option->getId() === $customFieldValue->getValue());

        if (!$isValidOption) {
            throw new InvalidArgumentException(sprintf('SingleSelect invalid option id "%s" for CustomFieldId "%s".', $customFieldValue->getValue(), $field->getId()));
        }
    }
}
