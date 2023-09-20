<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DateTime;

class DateHelper
{
    /**
     * Convert all DateTimes into Java Timestamps with milliseconds.
     *
     * @return array|null will only return null if $entities is null
     */
    public function convertDatesToLegacy(?array $entities): ?array
    {
        if (null === $entities) {
            return null;
        }

        foreach ($entities as $key => $val) {
            if (is_array($val)) {
                $entities[$key] = $this->convertDatesToLegacy($val);
            }
            if ($val instanceof DateTime) {
                // Java timestamps are milliseconds
                $entities[$key] = $val->getTimestamp() * 1000;
            }
        }

        return $entities;
    }

    /**
     * Converts an object of type DateTime, to a specific formatted string.
     *
     * @param DateTime $date , which is to be converted
     *
     * @return string date in the format: Y-m-dTH:i:s+0100
     */
    public function convertDateToString(DateTime $date)
    {
        $date = $date->format('Y-m-d H:i:s');
        $date[10] = 'T';

        return $date.'+0100';
    }
}
