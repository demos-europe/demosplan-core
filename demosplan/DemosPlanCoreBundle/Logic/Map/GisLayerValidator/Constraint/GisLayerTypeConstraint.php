<?php
declare(strict_types=1);


/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map\GisLayerValidator\Constraint;

use demosplan\DemosPlanCoreBundle\Logic\Map\GisLayerValidator\GisLayerTypeConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class GisLayerTypeConstraint extends Constraint
{
    /**
     * @var string
     */
    public $bplanAndScopeViolationMessage = 'validation.error.gislayer.bplan.and.scope';

    /**
     * @var string
     */
    public $overlayTypeRequiredViolationMessage = 'validation.error.gislayer.overlay.type.required';

    public function validatedBy(): string
    {
        return GisLayerTypeConstraintValidator::class;
    }

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}
