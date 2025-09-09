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

use demosplan\DemosPlanCoreBundle\Constraint\ConsistentOriginalStatementConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ConsistentOriginalStatementConstraintValidator extends ConstraintValidator
{
    /**
     * @param ConsultationToken $value
     */
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(ConsultationToken $token, ConsistentOriginalStatementConstraint $constraint): void
    {
        $statement = $token->getStatement();
        if (null === $statement) {
            return;
        }

        $originalOfStatement = $statement->getOriginal();
        $originalInToken = $token->getOriginalStatement();
        if ($originalInToken === $originalOfStatement) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{token}', $token->getId())
            ->setParameter('{statement}', $statement->getId())
            ->setParameter('{originalInToken}', $originalInToken->getId())
            ->setParameter('{originalOfStatement}', $originalOfStatement->getId())
            ->addViolation();
    }
}
