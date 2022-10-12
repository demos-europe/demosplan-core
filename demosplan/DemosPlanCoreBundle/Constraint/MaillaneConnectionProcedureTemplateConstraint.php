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

use demosplan\DemosPlanCoreBundle\Validator\MaillaneConnectionProcedureTemplateConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class MaillaneConnectionProcedureTemplateConstraint extends Constraint
{
    public $message = 'MaillaneConnection must never been related to a procedure template';

    public function validatedBy(): string
    {
        return MaillaneConnectionProcedureTemplateConstraintValidator::class;
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
