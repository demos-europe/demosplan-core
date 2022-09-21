<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\NonEmptyAnnotatedStatementPdfConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NonEmptyAnnotatedStatementPdfConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof AnnotatedStatementPdf) {
            $class = get_class(AnnotatedStatementPdf::class);
            throw new InvalidArgumentException("Given value must be of type {$class}");
        }

        if (!$constraint instanceof NonEmptyAnnotatedStatementPdfConstraint) {
            $class = get_class(NonEmptyAnnotatedStatementPdfConstraint::class);
            throw new InvalidArgumentException("Given constraint must be of type {$class}");
        }

        if (null !== $value->getStatement() && $value->getAnnotatedStatementPdfPages()->isEmpty()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{annotatedStatementPdf}', $value->getId())
                ->addViolation();
        }
    }
}
