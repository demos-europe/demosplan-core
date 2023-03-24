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

use demosplan\DemosPlanCoreBundle\Validator\ProcedureAllowedSegmentsConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ProcedureAllowedSegmentsConstraint extends Constraint
{
    public $message = 'Invalid connection between procedure extending segment access via other procedures: {procedureId}.';

    public function validatedBy(): string
    {
        return ProcedureAllowedSegmentsConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
