<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use demosplan\DemosPlanCoreBundle\Constraint\MaillaneConnectionProcedureTemplateConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\MaillaneConnection;

class MaillaneConnectionProcedureTemplateConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(MaillaneConnection $maillaneConnection, MaillaneConnectionProcedureTemplateConstraint $constraint): void
    {
        if ($maillaneConnection->getProcedure()->getMaster() && null !== $maillaneConnection->getProcedure()) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
