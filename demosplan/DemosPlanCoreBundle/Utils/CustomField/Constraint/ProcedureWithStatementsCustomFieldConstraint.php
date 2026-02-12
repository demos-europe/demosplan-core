<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint;

use demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator\ProcedureWithStatementsCustomFieldConstraintValidator;
use Symfony\Component\Validator\Constraint;

class ProcedureWithStatementsCustomFieldConstraint extends Constraint
{
    public string $message = 'CustomField cannot be updated: Procedure with statements';

    public function validatedBy(): string
    {
        return ProcedureWithStatementsCustomFieldConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
