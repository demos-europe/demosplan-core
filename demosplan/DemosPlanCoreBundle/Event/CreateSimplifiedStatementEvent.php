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

use DemosEurope\DemosplanAddon\Contracts\Events\CreateSimplifiedStatementEventInterface;
use DemosEurope\DemosplanAddon\Contracts\StatementCreatorInterface;
use Symfony\Component\HttpFoundation\Request;

class CreateSimplifiedStatementEvent extends DPlanEvent implements CreateSimplifiedStatementEventInterface
{
    private ?StatementCreatorInterface $emailStatementCreator = null;

    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getStatementFromEmailCreator(): ?StatementCreatorInterface
    {
        return $this->emailStatementCreator;
    }

    public function setStatementFromEmailCreator(?StatementCreatorInterface $emailStatementCreator): void
    {
        $this->emailStatementCreator = $emailStatementCreator;
    }
}
