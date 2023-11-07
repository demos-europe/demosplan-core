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

use demosplan\DemosPlanCoreBundle\Constraint\ConsistentAnonymousOrgaConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ConsistentAnonymousOrgaConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $this->validateTyped($value, $constraint);
    }

    protected function validateTyped(?Statement $statement, ConsistentAnonymousOrgaConstraint $constraint): void
    {
        if (null === $statement) {
            return;
        }

        $orga = $statement->getMeta()->getOrgaName();
        $department = $statement->getMeta()->getOrgaDepartmentName();
        if ((User::ANONYMOUS_USER_ORGA_NAME === $orga && User::ANONYMOUS_USER_DEPARTMENT_NAME !== $department)
            || (User::ANONYMOUS_USER_ORGA_NAME !== $orga && User::ANONYMOUS_USER_DEPARTMENT_NAME === $department)) {
            $this->context->buildViolation('statement.anonymous')
                ->setParameter('{{ externId }}', $statement->getExternId())
                ->addViolation();
        }
    }
}
