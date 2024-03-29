<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

class StatementMovementCollection extends ValueObject
{
    /**
     * @param list<StatementMovement> $procedures
     */
    public function __construct(
        protected readonly array $procedures,
        protected readonly int $total
    ) {
        $this->lock();
    }
}
