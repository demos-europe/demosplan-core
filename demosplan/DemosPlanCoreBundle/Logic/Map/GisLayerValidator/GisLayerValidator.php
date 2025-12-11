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

use demosplan\DemosPlanCoreBundle\Logic\Map\GisLayerValidator\Constraint\GisLayerTypeConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GisLayerValidator
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validateGisLayerData(array $data): array
    {
        $violations = [];

        // Validate using all constraints
        $constraintViolations = $this->validator->validate($data, [
            new GisLayerTypeConstraint(),
        ]);

        foreach ($constraintViolations as $violation) {
            $violations[] = [
                'type'    => 'error',
                'message' => $violation->getMessage(),
            ];
        }

        return $violations;
    }
}
