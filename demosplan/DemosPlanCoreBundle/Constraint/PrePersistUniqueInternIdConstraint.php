<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\PrePersistUniqueInternIdConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * This unique constraint of statement.internId of procedure is already checked by an ORM-UniqueConstraint.
 * To allow executing this check pre persisting and thereby on creation of a statement without persisting,
 * this additional constraint is used.
 * This allows to generate a specific message for user, before trigger the DB error.
 * Furthermore this can be used to improve performance.
 *
 * @Annotation
 */
class PrePersistUniqueInternIdConstraint extends Constraint
{
    public $message = 'statement.existing.internId';

    public function validatedBy(): string
    {
        return PrePersistUniqueInternIdConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
