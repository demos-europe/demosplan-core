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
            if (null === $procedure->getProcedureUiDefinition()) {
                $this->context->buildViolation(
                    $constraint->nonBlueprintProcedureUiDefinitionViolationMessage
                )->addViolation();
            }

            if (null === $procedure->getStatementFormDefinition()) {
                $this->context->buildViolation(
                    $constraint->nonBlueprintStatementFormDefinitionViolationMessage
                )->addViolation();
            }

            if (null === $procedure->getProcedureType()) {
                $this->context->buildViolation(
                    $constraint->nonBlueprintProcedureTypeViolationMessage
                )->addViolation();
            }
        }

        if ($isBlueprint) {
            if (null !== $procedure->getProcedureUiDefinition()) {
                $this->context->buildViolation(
                    $constraint->blueprintProcedureUiDefinitionViolationMessage
                )->addViolation();
            }

            if (null !== $procedure->getStatementFormDefinition()) {
                $this->context->buildViolation(
                    $constraint->blueprintStatementFormDefinitionViolationMessage
                )->addViolation();
            }

            if (null !== $procedure->getProcedureType()) {
                $this->context->buildViolation(
                    $constraint->blueprintProcedureTypeViolationMessage
                )->addViolation();
            }
        }
    }
}
