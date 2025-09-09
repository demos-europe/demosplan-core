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

use demosplan\DemosPlanCoreBundle\Validator\PostcodeConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class PostcodeConstraint extends Constraint
{
    public $message = 'statementMeta.import.invalidPostcode';

    public function validatedBy(): string
    {
        return PostcodeConstraintValidator::class;
    }
}
