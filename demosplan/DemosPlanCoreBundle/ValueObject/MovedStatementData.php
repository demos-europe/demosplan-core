<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

class MovedStatementData extends ValueObject
{
    public function __construct(
        protected readonly StatementMovementCollection $toThisProcedure,
        protected readonly StatementMovementCollection $fromThisProcedure
    ) {
        $this->lock();
    }
}
