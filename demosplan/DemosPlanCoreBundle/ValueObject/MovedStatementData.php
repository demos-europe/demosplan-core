<?php

declare(strict_types=1);

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
