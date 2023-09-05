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

class StatementMovement extends ValueObject
{
    public function __construct(
        protected readonly string $id,
        protected readonly string $title,
        protected readonly int $value
    ) {
        $this->lock();
    }
}
