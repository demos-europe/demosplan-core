<?php

declare(strict_types=1);

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
