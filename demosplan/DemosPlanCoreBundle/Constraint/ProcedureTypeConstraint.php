<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\ProcedureTypeConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ProcedureTypeConstraint extends Constraint
{
    /**
     * @var string
     */
    public $nonBlueprintProcedureUiDefinitionViolationMessage =
        'A (Non-Blueprint-)Procedure always needs a related ProcedureUiDefinition.';

    /**
     * @var string
     */
    public $nonBlueprintStatementFormDefinitionViolationMessage =
        'A (Non-Blueprint-)Procedure always needs a related StatementFormDefinition.';

    /**
     * @var string
     */
    public $nonBlueprintProcedureTypeViolationMessage =
        'A (Non-Blueprint-)Procedure always needs a related ProcedureType.';

    /**
     * @var string
     */
    public $blueprintProcedureUiDefinitionViolationMessage =
        'A Blueprint should never have a related ProcedureUiDefinition, because it will not be used.';

    /**
     * @var string
     */
    public $blueprintStatementFormDefinitionViolationMessage =
        'A Blueprint should never have a related StatementFormDefinition, because it will not be used.';

    /**
     * @var string
     */
    public $blueprintProcedureTypeViolationMessage =
        'A Blueprint should never have a related ProcedureType, because it will not be used.';

    public function validatedBy(): string
    {
        return ProcedureTypeConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
