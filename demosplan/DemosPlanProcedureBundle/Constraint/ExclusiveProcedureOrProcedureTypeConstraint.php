<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Constraint;

use demosplan\DemosPlanProcedureBundle\Validator\ExclusiveProcedureOrProcedureTypeConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ExclusiveProcedureOrProcedureTypeConstraint extends Constraint
{
    /**
     * @var string
     */
    public $procedureAndProcedureTypeViolationMessage = 'validation.error.exclusive.procedure.or.procedure.type:';

    /**
     * @var string
     */
    public $noProcedureOrProcedureTypeViolationMessage = 'validation.error.needed.procedure.or.procedure.type';

    public function validatedBy(): string
    {
        return ExclusiveProcedureOrProcedureTypeConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
