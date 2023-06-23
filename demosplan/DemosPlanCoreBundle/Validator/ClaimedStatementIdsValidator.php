<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\ClaimedStatementIdsConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementIdsInProcedureVO;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ClaimedStatementIdsValidator extends ConstraintValidator
{
    public function __construct(private readonly StatementService $statementService)
    {
    }

    /**
     * @param string|StatementIdsInProcedureVO|Statement|string[]|StatementIdsInProcedureVO[]|Statement[] $value
     * @param ClaimedStatementIdsConstraint                                                               $constraint
     *                                                                                                                {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $value) {
            return;
        }

        $valid = true;

        if (\is_array($value)) {
            foreach ($value as $singleValue) {
                $valid = $valid && $this->validateSingleValue($singleValue);
            }
        } else {
            $valid = $valid && $this->validateSingleValue($value);
        }

        if (true !== $valid) {
            $this->context->buildViolation($constraint->getMessage())
                ->addViolation();
        }
    }

    /**
     * @param string|StatementIdsInProcedureVO|Statement $value
     */
    protected function validateSingleValue($value): bool
    {
        $valid = true;

        // handle statement ID
        if (\is_string($value)) {
            $valid = $valid && $this->isStatementIdClaimed($value);
        }

        // handle StatementIdsInProcedureVO
        elseif ($value instanceof StatementIdsInProcedureVO) {
            foreach ($value->getStatementIds() as $statementId) {
                $valid = $valid && $this->isStatementIdClaimed($statementId);
            }
        }

        // handle Statement entity
        elseif ($value instanceof Statement) {
            $valid = $valid && $this->isStatementClaimed($value);
        }

        // handle unsupported type
        else {
            throw new UnexpectedTypeException($value, 'None of the supported types');
        }

        return $valid;
    }

    protected function isStatementClaimed(Statement $statement): bool
    {
        return $this->statementService->hasCurrentUserStatementAssignWriteRights($statement);
    }

    /**
     * @param string $statementId
     */
    protected function isStatementIdClaimed($statementId): bool
    {
        $statement = $this->statementService->getStatement($statementId);
        if (null === $statement) {
            return false;
        }

        return $this->isStatementClaimed($statement);
    }
}
