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

use Attribute;
use demosplan\DemosPlanCoreBundle\Validator\SegmentDraftJsonConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[Attribute]
class SegmentDraftJsonConstraint extends Constraint
{
    /**
     * @var string
     */
    public $message = 'error.segmentation.invalid_draft_json';

    public function validatedBy(): string
    {
        return SegmentDraftJsonConstraintValidator::class;
    }
}
