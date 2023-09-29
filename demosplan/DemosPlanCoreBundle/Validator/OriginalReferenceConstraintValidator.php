<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\OriginalReferenceConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Ensures that the given {@link Statement} has not set itself as original statement.
 *
 * @see OriginalReferenceConstraint to use this validator as annotation
 */
class OriginalReferenceConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof Statement) {
            throw new InvalidArgumentException('OriginalReferenceConstraint validation currently possible on statements only');
        }

        if (!$constraint instanceof OriginalReferenceConstraint) {
            throw new InvalidArgumentException('OriginalReferenceConstraint was expected');
        }

        $original = $value->getOriginal();

        if (null === $original) {
            return;
        }

        if ($original->getId() === $value->getId()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{statementId}', $value->getId())
                ->addViolation();
        }
    }
}
