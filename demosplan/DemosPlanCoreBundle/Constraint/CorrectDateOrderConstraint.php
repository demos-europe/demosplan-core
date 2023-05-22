<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\CorrectDateOrderConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class CorrectDateOrderConstraint extends Constraint
{
    public $message = 'statement.invalidDateOrder';

    public function validatedBy(): string
    {
        return CorrectDateOrderConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
