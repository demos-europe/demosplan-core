<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\StatementIdsInProcedureConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementIdsInProcedureVO;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class StatementIdsInProcedureValidator extends ConstraintValidator
{
    /** @var StatementService */
    private $statementService;

    public function __construct(StatementService $statementService)
    {
        $this->statementService = $statementService;
    }

    /**
     * @param StatementIdsInProcedureVO         $statementIdsInProcedure
     * @param StatementIdsInProcedureConstraint $constraint
     *                                                                   {@inheritdoc}
     */
    public function validate($statementIdsInProcedure, Constraint $constraint)
    {
        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $statementIdsInProcedure) {
            return;
        }

        if (!($statementIdsInProcedure instanceof StatementIdsInProcedureVO)) {
            throw new UnexpectedTypeException($statementIdsInProcedure, 'StatementIdsInProcedureVO');
        }

        $procedureId = $statementIdsInProcedure->getProcedureId();
        $statementIds = $statementIdsInProcedure->getStatementIds();

        $statementsInProcedure = $this->statementService->getStatementsInProcedureWithId($procedureId, $statementIds);
        $statementsInProcedure = collect($statementsInProcedure)->filter(function ($statement) {
            /* @var Statement $statement */
            return !$statement->isPlaceholder();
        })->all();
        $invalidStatementIdsCount = count($statementIds) - count($statementsInProcedure);

        if (0 === count($statementsInProcedure)) {
            $this->context->buildViolation($constraint->getNoneFoundMessage())
                ->setParameter('{{ invalidStatementCount }}', $invalidStatementIdsCount)
                ->addViolation();
        } elseif (0 !== $invalidStatementIdsCount) {
            $statementsInProcedureExternIds = collect($statementsInProcedure)->map(function ($statement) {
                /* @var Statement $statement */
                return $statement->getExternId();
            })->all();
            $this->context->buildViolation($constraint->getSomeNotFoundMessage())
                ->setParameter('{{ invalidStatementCount }}', $invalidStatementIdsCount)
                ->setParameter('{{ validStatementsCount }}', count($statementsInProcedure))
                ->setParameter('{{ validStatementIds }}', implode(', ', $statementsInProcedureExternIds))
                ->addViolation();
        }
    }
}
