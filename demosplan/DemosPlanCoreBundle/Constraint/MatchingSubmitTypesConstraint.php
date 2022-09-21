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

use demosplan\DemosPlanCoreBundle\Validator\MatchingSubmitTypesConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class MatchingSubmitTypesConstraint extends Constraint
{
    public $message = 'statement.invalidSubmitType';

    public function validatedBy(): string
    {
        return MatchingSubmitTypesConstraintValidator::class;
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
