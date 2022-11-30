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

use Symfony\Component\HttpFoundation\Request;
use demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement\ManualSimplifiedStatementCreator;

class CreateSimplifiedStatementEvent extends DPlanEvent
{
    private ?ManualSimplifiedStatementCreator $emailStatementCreator = null;

    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getStatementFromEmailCreator (): ?ManualSimplifiedStatementCreator
    {
        return $this->emailStatementCreator;
    }

    public function setStatementFromEmailCreator (?ManualSimplifiedStatementCreator $emailStatementCreator): void
    {
       $this->emailStatementCreator = $emailStatementCreator;
    }
}
