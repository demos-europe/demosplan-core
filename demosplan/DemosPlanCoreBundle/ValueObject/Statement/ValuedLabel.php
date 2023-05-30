<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string getLabel()
 * @method mixed  getValue()
 */
class ValuedLabel extends ValueObject
{
    /** @var string */
    protected $label;
    /** @var mixed */
    protected $value;

    public static function create(string $label, $value): self
    {
        $self = new self();
        $self->label = $label;
        $self->value = $value;
        $self->lock();

        return $self;
    }
}
