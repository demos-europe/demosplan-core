<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

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
        if ($maillaneConnection->getProcedure()->getMaster()) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
