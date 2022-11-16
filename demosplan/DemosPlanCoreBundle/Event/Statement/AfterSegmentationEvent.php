<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class AfterSegmentationEvent extends DPlanEvent
{
    private Statement $statement;

    public function __construct(Statement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * @return Statement
     */
    public function getStatement(): Statement
    {
        return $this->statement;
    }

    /**
     * @param Statement $statement
     */
    public function setStatement(Statement $statement): void
    {
        $this->statement = $statement;
    }

}
