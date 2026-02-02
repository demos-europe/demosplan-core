<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint;

use demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator\ProcedureWithStatementsCustomFieldConstraintValidator;
use Symfony\Component\Validator\Constraint;

class ProcedureWithStatementsCustomFieldConstraint extends Constraint
{
    public string $message = 'custom_field.procedure_with_statements.cannot_update';

    public function validatedBy(): string
    {
        return ProcedureWithStatementsCustomFieldConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
