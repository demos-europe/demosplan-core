<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use demosplan\DemosPlanCoreBundle\Constraint\RoleAllowedConstraint;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;

/**
 * @see RoleAllowedConstraint for usage as annotation
 */
class RoleAllowedConstraintValidator extends ConstraintValidator
{
    /** @var GlobalConfigInterface */
    private $globalConfig;

    public function __construct(GlobalConfigInterface $globalConfig)
    {
        $this->globalConfig = $globalConfig;
    }

    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(UserRoleInCustomer $value, RoleAllowedConstraint $constraint): void
    {
        $roleCode = $value->getRole()->getCode();
        $roleIsAllowed = in_array($roleCode, $this->globalConfig->getRolesAllowed(), true);

        if (!$roleIsAllowed) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{role}', $roleCode)
                ->addViolation();
        }
    }
}
