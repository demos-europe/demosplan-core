<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\ProcedureTemplateMaillaneConnectionConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ProcedureTemplateMaillaneConnectionConstraint extends Constraint
{
    public $message = 'Procedure templates must never have a related maillaneConnection';

    public function validatedBy(): string
    {
        return ProcedureTemplateMaillaneConnectionConstraintValidator::class;
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
