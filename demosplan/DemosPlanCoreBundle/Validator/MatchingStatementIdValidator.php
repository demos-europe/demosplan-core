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

use demosplan\DemosPlanCoreBundle\Constraint\MatchingFieldValueInSegments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class MatchingStatementIdValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    /**
     * Checks for existing statementId (extern) in list of segments which also includes a statementId,
     * to ensure the statement can be related to a segment in this list of segments.
     *
     * @param array<string, mixed> $statementData
     */
    private function validateTyped(array $statementData, MatchingFieldValueInSegments $constraint): void
    {
        $statementExternId = $statementData[$constraint->statementIdIdentifier];

        if (!\array_key_exists($statementExternId, $constraint->segments)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ fieldName }}', $constraint->statementIdIdentifier)
                ->setParameter('{{ statementWorksheetTitle }}', $constraint->statementWorksheetTitle)
                ->setParameter('{{ segmentWorksheetTitle }}', $constraint->segmentWorksheetTitle)
                ->addViolation();
        }
    }
}
