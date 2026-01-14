<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Contraint;

use demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator\ValidMultiSelectValueConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that a value is valid for a MultiSelectField.
 *
 * @Annotation
 */
class ValidMultiSelectValueConstraint extends Constraint
{
    public string $notArrayMessage = 'Value must be an array';
    public string $requiredEmptyMessage = 'Required field cannot be empty';
    public string $elementNotStringMessage = 'Element must be a string';
    public string $invalidOptionIdMessage = 'Invalid option ID: {{ optionId }}';

    // Store the MultiSelectField to validate against
    public mixed $field = null;

    public function validatedBy(): string
    {
        return ValidMultiSelectValueConstraintValidator::class;
    }
}
