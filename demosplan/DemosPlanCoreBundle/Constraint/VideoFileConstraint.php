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

use demosplan\DemosPlanCoreBundle\Validator\VideoFileConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class VideoFileConstraint extends Constraint
{
    public $message = 'file.video.invalid';

    public function validatedBy(): string
    {
        return VideoFileConstraintValidator::class;
    }
}
