<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator;

use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint\ProcedureWithStatementsCustomFieldConstraint;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProcedureWithStatementsCustomFieldConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ProcedureService $procedureService,
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ProcedureWithStatementsCustomFieldConstraint) {
            throw new InvalidArgumentException('ProcedureWithStatementsCustomFieldConstraint was expected');
        }

        if (!$value instanceof CustomFieldConfiguration) {
            return;
        }

        if (CustomFieldSupportedEntity::procedure->value !== $value->getSourceEntityClass()
            && CustomFieldSupportedEntity::statement->value !== $value->getTargetEntityClass()) {
            return;
        }

        $procedureId = $value->getSourceEntityId();
        $counts = $this->procedureService->getStatementsCounts([$procedureId]);

        $statementCounts =  $counts[$procedureId] ?? 0;

        // Check if procedure has statements
        if ($statementCounts > 0) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{procedureId}', $procedureId)
                ->addViolation();
        }
    }
}
