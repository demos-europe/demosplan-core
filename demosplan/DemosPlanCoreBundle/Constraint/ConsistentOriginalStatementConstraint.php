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

use demosplan\DemosPlanCoreBundle\Validator\ConsistentOriginalStatementConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ConsistentOriginalStatementConstraint extends Constraint
{
    public $message = 'Inconsistent original statements in token with ID {token}. ConsultationToken is related to a statement ({statement}) and an original statement ({originalInToken}). But the the latter is not the same as the original statement of the statement, but {originalOfStatement} instead.';

    public function validatedBy(): string
    {
        return ConsistentOriginalStatementConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
