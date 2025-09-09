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

use demosplan\DemosPlanCoreBundle\Constraint\ProcedureMasterTemplateConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProcedureMasterTemplateConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(Procedure $procedure, ProcedureMasterTemplateConstraint $constraint): void
    {
        if (!$procedure->getMaster() && $procedure->isMasterTemplate()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{procedureId}', $procedure->getId())
                ->addViolation();
        }
    }
}
