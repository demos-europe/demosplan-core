<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\ProcedureTemplateConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ProcedureTemplateConstraint extends Constraint
{
    public $message = 'Procedure templates must have their master flag set to true: {procedureId}';

    public function validatedBy(): string
    {
        return ProcedureTemplateConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
