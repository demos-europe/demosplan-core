<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\OriginalReferenceConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class OriginalReferenceConstraint extends Constraint
{
    public $message = 'Original statement of statement must not be itself: {statementId}';

    public function validatedBy(): string
    {
        return OriginalReferenceConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
