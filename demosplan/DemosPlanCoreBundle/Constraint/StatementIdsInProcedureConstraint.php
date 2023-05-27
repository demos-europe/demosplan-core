<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\StatementIdsInProcedureValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class StatementIdsInProcedureConstraint extends Constraint
{
    /** @var string */
    protected $someNotFoundMessage = '{{ invalidStatementCount }} statements could not be found in the procedure. The IDs of the {{ validStatementsCount }} following statements have been found: {{ validStatementIds }}.';

    protected $noneFoundMessage = 'None of the {{ invalidStatementCount }} statements have been found.';

    public function validatedBy(): string
    {
        return StatementIdsInProcedureValidator::class;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function getSomeNotFoundMessage(): string
    {
        return $this->someNotFoundMessage;
    }

    public function getNoneFoundMessage(): string
    {
        return $this->noneFoundMessage;
    }
}
