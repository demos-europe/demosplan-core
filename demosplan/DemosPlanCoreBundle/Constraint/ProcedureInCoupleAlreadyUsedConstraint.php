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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Validator\ProcedureInCoupleAlreadyUsedConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * To be used on the {@link ProcedureCoupleToken} class. Checks if the procedures set in
 * {@link ProcedureCoupleToken::$sourceProcedure} and {@link ProcedureCoupleToken::$targetProcedure}
 * are somehow already used in another (persisted) {@link ProcedureCoupleToken}, which would be
 * invalid. In such case for either property an individual violation will be raised to prevent
 * circular procedure couplings.
 *
 * @Annotation
 */
class ProcedureInCoupleAlreadyUsedConstraint extends Constraint
{
    /**
     * @var string
     */
    public $targetProcedureMessage = 'procedure.token.circular.coupling.target';

    /**
     * @var string
     */
    public $sourceProcedureMessage = 'procedure.token.circular.coupling.source';

    public function validatedBy(): string
    {
        return ProcedureInCoupleAlreadyUsedConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
