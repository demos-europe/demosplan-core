<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;

class EndDateSorter extends ProcedureTimestampSorter
{
    public function sortLegacyArrays(array $procedures): array
    {
        return $this->sortArbitrary($procedures, [self::class, 'getLegacyArrayTimestamp']);
    }

    public function sortEntities(array $procedures): array
    {
        return $this->sortArbitrary($procedures, [self::class, 'getEntityTimestamp']);
    }

    /**
     * Returns the endDate timestamp of a procedure entity instance.
     *
     * @param Procedure $procedure
     */
    protected static function getEntityTimestamp($procedure/* , $key */): int
    {
        return $procedure->getEndDateTimestamp();
    }

    /**
     * Returns the endDate timestamp of a procedure legacy array.
     *
     * @param array $procedure
     */
    protected static function getLegacyArrayTimestamp($procedure/* , $key */): int
    {
        return $procedure['endDateTimestamp'];
    }
}
