<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\ProcedureTypeConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProcedureTypeConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(
        Procedure $procedure,
        ProcedureTypeConstraint $constraint): void
    {
        $isBlueprint = $procedure->getMaster();

        if (!$isBlueprint) {
            if (!$procedure->getProcedureUiDefinition() instanceof ProcedureUiDefinition) {
                $this->context->buildViolation(
                    $constraint->nonBlueprintProcedureUiDefinitionViolationMessage
                )->addViolation();
            }

            if (!$procedure->getStatementFormDefinition() instanceof StatementFormDefinition) {
                $this->context->buildViolation(
                    $constraint->nonBlueprintStatementFormDefinitionViolationMessage
                )->addViolation();
            }

            if (!$procedure->getProcedureType() instanceof ProcedureType) {
                $this->context->buildViolation(
                    $constraint->nonBlueprintProcedureTypeViolationMessage
                )->addViolation();
            }
        }

        if ($isBlueprint) {
            if ($procedure->getProcedureUiDefinition() instanceof ProcedureUiDefinition) {
                $this->context->buildViolation(
                    $constraint->blueprintProcedureUiDefinitionViolationMessage
                )->addViolation();
            }

            if ($procedure->getStatementFormDefinition() instanceof StatementFormDefinition) {
                $this->context->buildViolation(
                    $constraint->blueprintStatementFormDefinitionViolationMessage
                )->addViolation();
            }

            if ($procedure->getProcedureType() instanceof ProcedureType) {
                $this->context->buildViolation(
                    $constraint->blueprintProcedureTypeViolationMessage
                )->addViolation();
            }
        }
    }
}
