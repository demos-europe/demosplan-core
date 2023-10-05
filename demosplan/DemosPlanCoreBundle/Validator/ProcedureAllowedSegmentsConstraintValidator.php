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

use demosplan\DemosPlanCoreBundle\Constraint\ProcedureAllowedSegmentsConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Will be executed when a {@link Procedure} class was annotated wtih {@link ProcedureAllowedSegmentsConstraint}.
 *
 * Ensures that:
 *
 * * the procedure is not a template procedure
 * * none of the procedures referenced in its {@link ProcedureSettings::$allowedSegmentAccessProcedures}
 *   are template procedures
 * * the procedure does not reference itself in its its {@link ProcedureSettings::$allowedSegmentAccessProcedures}
 */
class ProcedureAllowedSegmentsConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(
        Procedure $procedure,
        ProcedureAllowedSegmentsConstraint $constraint
    ): void {
        $procedureId = $procedure->getId();
        $isTemplate = $procedure->isMasterTemplate() || $procedure->getMaster();
        $allowedProcedures = $procedure->getSettings()->getAllowedSegmentAccessProcedures();
        $referencesTemplate = $allowedProcedures
            ->exists(static fn (int $key, Procedure $procedure): bool => $procedure->isMasterTemplate() || $procedure->getMaster());
        $selfReference = $allowedProcedures
            ->map(static fn (Procedure $procedure): string => $procedure->getId())
            ->contains($procedure);

        if ($isTemplate || $referencesTemplate || $selfReference) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{procedureId}', $procedureId)
                ->addViolation();
        }
    }
}
