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

use Carbon\Carbon;
use DateTime;
use demosplan\DemosPlanCoreBundle\Constraint\DateInFutureConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DateInFutureConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(?DateTime $dateTime, DateInFutureConstraint $constraint): void
    {
        if (null === $dateTime) {
            return;
        }

        if (!Carbon::instance($dateTime)->isFuture()) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
