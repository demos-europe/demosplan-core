<?php

declare(strict_types=1);


/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map\GisLayerValidator;

use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;

class GisLayerTypeConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(array $gislayer, Constraint $constraint): void
    {
        // entweder Planzeichnung oder Geltungsbereich, nicht beide
        if ($gislayer['bplan'] && $gislayer['scope']) {
            $this->context->buildViolation(
                $constraint->bplanAndScopeViolationMessage
            )->addViolation();
        }
        // wenn Planzeichnung oder Geltungsbereich, dann muss es ein Overlay sein
        if (($gislayer['bplan'] || $gislayer['scope']) && 'overlay' !== $gislayer['type']) {
            $this->context->buildViolation(
                $constraint->overlayTypeRequiredViolationMessage
            )->addViolation();
        }

    }
}

