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

class PublicParticipationEndDateSorter extends ProcedureTimestampSorter
{
    public function sortLegacyArrays(array $procedures): array
    {
        return $this->sortArbitrary($procedures, $this->getLegacyArrayTimestamp(...));
    }

    public function sortEntities(array $procedures): array
    {
        return $this->sortArbitrary($procedures, $this->getEntityTimestamp(...));
    }

    /**
     * Returns the publicParticipationEndDate timestamp of a procedure entity instance.
     *
     * @param Procedure $procedure
     */
    protected function getEntityTimestamp($procedure/* , $key */): int
    {
        return $procedure->getPublicParticipationEndDateTimestamp();
    }

    /**
     * Returns the publicParticipationEndDate timestamp of a procedure legacy array.
     *
     * @param array $procedure
     */
    protected function getLegacyArrayTimestamp($procedure/* , $key */): int
    {
        return $procedure['publicParticipationEndDateTimestamp'];
    }
}
