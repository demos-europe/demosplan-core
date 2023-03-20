<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\UserWithMatchingDepartmentInOrgaConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UserWithMatchingDepartmentInOrgaConstraint extends Constraint
{
    /** @var string */
    public $message = 'The User with ID {userId} has a department set which is in a different Orga ({departmentOrgaId}) than the Orga ({orgaId}) set in the User.';

    public function validatedBy(): string
    {
        return UserWithMatchingDepartmentInOrgaConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
