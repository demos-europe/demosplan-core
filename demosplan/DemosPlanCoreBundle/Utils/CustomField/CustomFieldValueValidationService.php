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
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator\CustomFieldValueValidationStrategyInterface;

/**
 * Orchestrates validation of custom field values by delegating to field-type-specific strategies.
 * This ensures values meet field requirements before storage.
 */
class CustomFieldValueValidationService
{
    /**
     * @param iterable<CustomFieldValueValidationStrategyInterface> $strategies
     */
    public function __construct(private readonly iterable $strategies)
    {
    }

    /**
     * Validate a value against a custom field definition.
     *
     * @param CustomFieldInterface $field The field definition
     * @param mixed                $value The value to validate
     *
     * @throws ViolationsException      When validation fails
     * @throws InvalidArgumentException When no strategy found for field type
     */
    public function validate(CustomFieldInterface $field, CustomFieldValue $value): void
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($field)) {
                $strategy->validate($field, $value);

                return;
            }
        }

        throw new InvalidArgumentException(sprintf('No validation strategy found for custom field type "%s". Each field type must have a dedicated validation strategy.', $field::class));
    }
}
