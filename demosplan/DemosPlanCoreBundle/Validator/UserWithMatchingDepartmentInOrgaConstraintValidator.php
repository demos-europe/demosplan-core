<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\UserWithMatchingDepartmentInOrgaConstraint;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UserWithMatchingDepartmentInOrgaConstraintValidator extends ConstraintValidator
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(User $user, UserWithMatchingDepartmentInOrgaConstraint $constraint): void
    {
        $department = $user->getDepartment();
        $orga = $user->getOrga();
        if (null !== $orga && null !== $department) {
            $departmentOrga = $department->getOrga();
            if ($departmentOrga !== $orga) {
                $this->logger->warning($constraint->message, [
                    'userId'           => $user->getId(),
                    'orgaId'           => $orga->getId(),
                    'departmentOrgaId' => null !== $departmentOrga ? $departmentOrga->getId() : null,
                ]);
                // TODO: being extra careful we only log violations for now instead of adding an actual violation to the context that may throw an exception if not handled correctly
                /*$this->context->buildViolation($constraint->message)
                    ->setParameter('{userId}', $user->getId())
                    ->setParameter('{orgaId}', $orga->getId())
                    ->setParameter('{departmentOrgaId}', null !== $departmentOrga ? $departmentOrga->getId() : null)
                    ->addViolation();*/
            }
        }
    }
}
