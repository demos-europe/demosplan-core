<?php

declare(strict_types=1);

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
