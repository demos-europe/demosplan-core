<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\IsOriginalStatementConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsOriginalStatementConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateStatement($value, $constraint);
    }

    /**
     * Validates if the given {@link Statement} is an original statement.
     */
    public function validateStatement(Statement $value, IsOriginalStatementConstraint $constraint): void
    {
        if (!$value->isOriginal()) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
