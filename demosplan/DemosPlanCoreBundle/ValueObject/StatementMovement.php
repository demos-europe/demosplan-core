<?php

declare(strict_types=1);

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
