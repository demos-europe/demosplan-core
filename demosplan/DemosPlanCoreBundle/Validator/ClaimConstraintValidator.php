<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\ClaimConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if the user that has the given statement currently claimed is allowed
 * to be the assignee of said statement.
 *
 * @see ClaimConstraint for usage as annotation
 */
class ClaimConstraintValidator extends ConstraintValidator
{
    /** @var ProcedureService */
    private $procedureService;

    public function __construct(ProcedureService $procedureService)
    {
        $this->procedureService = $procedureService;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof Statement) {
            throw new InvalidArgumentException('ClaimConstraint validation currently possible on statements only');
        }

        if (!$constraint instanceof ClaimConstraint) {
            throw new InvalidArgumentException('ClaimConstraint was expected');
        }

        $user = $value->getAssignee();
        $procedureId = $value->getProcedure()->getId();
        if (null === $user) {
            return;
        }

        $authorized = $this->procedureService->isUserAuthorized($procedureId, $user);
        if (true !== $authorized) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{statementId}', $value->getId())
                ->setParameter('{userId}', $user->getId())
                ->addViolation();
        }
    }
}
