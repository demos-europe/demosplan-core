<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement\StatementFromEmailCreator;
use Symfony\Component\HttpFoundation\Request;

class ImportingStatementViaEmailEvent  extends DPlanEvent
{
    private ?StatementFromEmailCreator $emailStatementCreator = null;

    public function getStatementFromEmailCreator (): ?StatementFromEmailCreator
    {
        return $this->emailStatementCreator;
    }

    public function setStatementFromEmailCreator (?StatementFromEmailCreator $emailStatementCreator): void
    {
       $this->emailStatementCreator = $emailStatementCreator;
    }
}
