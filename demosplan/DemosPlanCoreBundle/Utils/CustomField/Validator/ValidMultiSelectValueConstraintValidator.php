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

use demosplan\DemosPlanCoreBundle\CustomField\MultiSelectField;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Contraint\ValidMultiSelectValueConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidMultiSelectValueConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidMultiSelectValueConstraint) {
            throw new UnexpectedTypeException($constraint, ValidMultiSelectValueConstraint::class);
        }

        $field = $constraint->field;
        if (!$field instanceof MultiSelectField) {
            return; // Skip if no field provided
        }

        // Null is always valid
        if (null === $value) {
            return;
        }

        // Must be array
        if (!is_array($value)) {
            $this->context->buildViolation($constraint->notArrayMessage)
                ->addViolation();

            return;
        }

        // Required fields cannot be empty
        if ($field->getRequired() && [] === $value) {
            $this->context->buildViolation($constraint->requiredEmptyMessage)
                ->addViolation();

            return;
        }

        // Empty array OK for non-required
        if ([] === $value) {
            return;
        }

        // Validate each element
        foreach ($value as $index => $optionId) {
            if (!is_string($optionId)) {
                $this->context->buildViolation($constraint->elementNotStringMessage)
                    ->atPath("[{$index}]")
                    ->addViolation();
                continue;
            }

            if (null === $field->getCustomOptionValueById($optionId)) {
                $this->context->buildViolation($constraint->invalidOptionIdMessage)
                    ->atPath("[{$index}]")
                    ->setParameter('{{ optionId }}', $optionId)
                    ->addViolation();
            }
        }
    }
}
