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

use demosplan\DemosPlanCoreBundle\Validator\ProcedureMasterTemplateConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ProcedureMasterTemplateConstraint extends Constraint
{
    public $message = 'Only procedure templates can be a master template: {procedureId}';

    public function validatedBy(): string
    {
        return ProcedureMasterTemplateConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
