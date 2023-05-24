<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\ClaimConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ClaimConstraint extends Constraint
{
    public $message = 'Claiming of statement with ID {statementId} not allowed for user with ID {userId}.';

    public function validatedBy(): string
    {
        return ClaimConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
