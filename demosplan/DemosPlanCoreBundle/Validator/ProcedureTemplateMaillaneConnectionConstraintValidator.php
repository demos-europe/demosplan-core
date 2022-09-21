<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\ProcedureTemplateMaillaneConnectionConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProcedureTemplateMaillaneConnectionConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(Procedure $procedure, ProcedureTemplateMaillaneConnectionConstraint $constraint): void
    {
        if ($procedure->getMaster() && null !== $procedure->getMaillaneConnection()) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
