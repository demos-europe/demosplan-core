<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\PostcodeConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PostcodeConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    /**
     * Incoming value of postcode can be string as well as integer.
     */
    private function validateTyped($value, PostcodeConstraint $constraint): void
    {
        if ('' === $value) {
            return;
        }
        if (is_int($value) && 999 < $value && 100000 > $value) {
            return;
        }
        if (is_string($value) && is_numeric($value) && 5 === strlen($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
