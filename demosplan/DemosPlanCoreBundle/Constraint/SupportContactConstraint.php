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

use Attribute;
use demosplan\DemosPlanCoreBundle\Validator\SupportContactConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[Attribute]
class SupportContactConstraint extends Constraint
{
    final public const WRONG_SUPPORT_TYPE = 'error.platform.support.with.customer.context';
    final public const NO_TITLE_MESSAGE = 'error.mandatoryfield.title';
    final public const MISSING_CONTACT_MESSAGE = 'error.mandatoryfield.mail.or.phone';

    public function validatedBy(): string
    {
        return SupportContactConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
