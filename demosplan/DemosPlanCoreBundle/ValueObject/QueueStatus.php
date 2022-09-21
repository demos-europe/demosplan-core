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

/**
 * @method int getTotal()
 * @method int getProcessing()
 */
class QueueStatus extends ValueObject
{
    /**
     * @var int
     */
    protected $total;

    /**
     * @var int
     */
    protected $processing;

    public function __construct(int $total, int $processing)
    {
        $this->total = $total;
        $this->processing = $processing;

        $this->lock();
    }
}
