<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\AllRolesInGroupPresentConstraint;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class AllRolesInGroupPresentConstraintValidator extends ConstraintValidator
{
    /**
     * @var RoleService
     */
    private $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(Collection $actualRoles, AllRolesInGroupPresentConstraint $constraint): void
    {
        if ([] === $constraint->groupCodes) {
            return;
        }

        $groupCodes = implode(', ', $constraint->groupCodes);
        $neededRoles = $this->roleService->getUserRolesByGroupCodes($constraint->groupCodes);
        array_map(function (Role $neededRole) use ($actualRoles, $constraint, $groupCodes): void {
            if (!$actualRoles->contains($neededRole)) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->setParameter('{roleCode}', $neededRole->getCode())
                    ->setParameter('{groupCodes}', $groupCodes)
                    ->addViolation();
            }
        }, $neededRoles);
    }
}
