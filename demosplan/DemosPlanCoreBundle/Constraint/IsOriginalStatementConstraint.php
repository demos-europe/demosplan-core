<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\IsOriginalStatementConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class IsOriginalStatementConstraint extends Constraint
{
    /** @var string */
    public $message = 'The given value is not an original statement.';

    public function validatedBy(): string
    {
        return IsOriginalStatementConstraintValidator::class;
    }
}
