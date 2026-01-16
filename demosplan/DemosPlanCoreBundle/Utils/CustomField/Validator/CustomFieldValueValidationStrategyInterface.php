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

/**
 * Strategy for validating custom field values before storage.
 * Each field type should have its own dedicated implementation.
 */
interface CustomFieldValueValidationStrategyInterface
{
    /**
     * Check if this strategy handles the given field type.
     *
     * @param CustomFieldInterface $field The custom field definition
     *
     * @return bool True if this strategy can validate values for this field type
     */
    public function supports(CustomFieldInterface $field): bool;

    /**
     * Validate a value against the field definition.
     */
    public function validate(CustomFieldInterface $field, CustomFieldValue $customFieldValue): void;
}
