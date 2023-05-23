<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\ExclusiveProcedureOrProcedureTypeConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ExclusiveProcedureOrProcedureTypeConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    /**
     * @param StatementFormDefinition|ProcedureUiDefinition $definition
     */
    private function validateTyped(
        $definition,
        ExclusiveProcedureOrProcedureTypeConstraint $constraint): void
    {
        $hasProcedureType = $definition->getProcedureType() instanceof ProcedureType;
        $hasProcedure = $definition->getProcedure() instanceof Procedure;

        if ($hasProcedureType && $hasProcedure) {
            $this->context->buildViolation(
                $constraint->procedureAndProcedureTypeViolationMessage
            )->addViolation();
        }

        if (!$hasProcedureType && !$hasProcedure) {
            $this->context->buildViolation(
                $constraint->noProcedureOrProcedureTypeViolationMessage
            )->addViolation();
        }
    }
}
