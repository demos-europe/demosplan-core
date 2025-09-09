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

use demosplan\DemosPlanCoreBundle\Validator\SimilarStatementSubmittersSameProcedureConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class SimilarStatementSubmittersSameProcedureConstraint extends Constraint
{
    /**
     * @var string
     */
    public $message = 'similarStatementSubmitter.procedureMismatch';

    public function validatedBy(): string
    {
        return SimilarStatementSubmittersSameProcedureConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
