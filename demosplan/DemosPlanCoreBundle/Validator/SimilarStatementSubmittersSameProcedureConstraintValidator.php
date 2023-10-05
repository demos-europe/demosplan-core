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

use demosplan\DemosPlanCoreBundle\Constraint\SimilarStatementSubmittersSameProcedureConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

use function is_string;

class SimilarStatementSubmittersSameProcedureConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    public function validateTyped(
        Statement $statement,
        SimilarStatementSubmittersSameProcedureConstraint $constraint
    ): void {
        $procedureId = $statement->getProcedure()->getId();
        if (is_string($procedureId)) {
            $mismatch = $statement->getSimilarStatementSubmitters()
                ->exists(static fn (int $key, ProcedurePerson $person): bool => $person->getProcedure()->getId() !== $procedureId);

            if (!$mismatch) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->addViolation();
    }
}
