<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Document\BthgKompassAnswer;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class EnrichDataOfStatementDetailViewEvent extends DPlanEvent
{
    /**
     * @var array<int, BthgKompassAnswer>
     */
    private $bthgKompassAnswers = [];

    /**
     * @return array<int, BthgKompassAnswer>
     */
    public function getBthgKompassAnswers(): array
    {
        return $this->bthgKompassAnswers;
    }

    public function setBthgKompassAnswers(array $bthgKompassAnswers): void
    {
        $this->bthgKompassAnswers = $bthgKompassAnswers;
    }
}
