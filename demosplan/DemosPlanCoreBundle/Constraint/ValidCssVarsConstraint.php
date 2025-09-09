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

use demosplan\DemosPlanCoreBundle\Validator\ValidCssVarsConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ValidCssVarsConstraint extends Constraint
{
    public $ymlExceptionMessage = 'branding.yaml.invalid';
    public $invalidVarMessage = 'branding.var.invalid';
    public $invalidColorMessage = 'branding.color.invalid';

    public function validatedBy(): string
    {
        return ValidCssVarsConstraintValidator::class;
    }
}
