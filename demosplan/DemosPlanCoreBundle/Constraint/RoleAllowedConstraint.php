<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\RoleAllowedConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class RoleAllowedConstraint extends Constraint
{
    /** @var string */
    public $message = 'Role is not allowed in this project: {role}';

    public function validatedBy(): string
    {
        return RoleAllowedConstraintValidator::class;
    }
}
