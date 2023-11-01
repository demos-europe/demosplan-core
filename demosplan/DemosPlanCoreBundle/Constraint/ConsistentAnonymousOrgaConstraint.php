<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Constraint;

use Attribute;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Validator\ConsistentAnonymousOrgaConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * If {@link StatementMeta::$orgaName} is set to {@link User::ANONYMOUS_USER_ORGA_NAME} then
 * {@link StatementMeta::$orgaDepartmentName} must be set to {@link User::ANONYMOUS_USER_DEPARTMENT_NAME} and the other
 * way around.
 *
 * @Annotation
 */
#[Attribute]
class ConsistentAnonymousOrgaConstraint extends Constraint
{
    public function validatedBy(): string
    {
        return ConsistentAnonymousOrgaConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
