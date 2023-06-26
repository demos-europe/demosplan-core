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

use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;

/**
 * @method string getDay()
 * @method array  getTimes()
 */
class HistoryDay extends ValueObject
{
    /**
     * @var string
     */
    protected $day;

    /**
     * @var array<int, HistoryTime>
     */
    protected $times;

    /**
     * @param array<string, array<int, EntityContentChange>> $times
     */
    public function __construct(array $times, string $day)
    {
        $this->day = $day;
        $this->times = array_values(array_map([HistoryTime::class, 'create'], $times, array_keys($times)));
        $this->lock();
    }

    /**
     * @param array<string, array<int, EntityContentChange>> $times
     */
    public static function create(array $times, string $day): self
    {
        return new self($times, $day);
    }
}
