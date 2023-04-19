<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\ValueObject;

class PriorityPair extends ValueObject
{
    public function __construct(
        protected readonly string $key,
        protected readonly string $label
    ) {
        $this->lock();
    }
}
