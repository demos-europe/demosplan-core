<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Filters;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string      getId()
 * @method string      getLabel()
 * @method string|null getDescription()
 * @method int         getCount()
 * @method bool        getSelected()
 */
class AggregationFilterItem extends ValueObject
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var int
     */
    protected $count;

    /**
     * @var bool
     */
    protected $selected;

    public function __construct(
        string $id,
        string $label,
        ?string $description,
        int $count,
        bool $selected
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->description = $description;
        $this->count = $count;
        $this->selected = $selected;
        $this->lock();
    }
}
