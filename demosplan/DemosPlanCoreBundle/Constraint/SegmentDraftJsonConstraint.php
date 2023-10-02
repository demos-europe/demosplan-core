<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Constraint;

use Attribute;
use demosplan\DemosPlanCoreBundle\Validator\SegmentDraftJsonConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[Attribute]
class SegmentDraftJsonConstraint extends Constraint
{
    public function validatedBy(): string
    {
        return SegmentDraftJsonConstraintValidator::class;
    }
}
