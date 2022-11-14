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

class ImportingStatementViaEmailEvent  extends DPlanEvent
{

    private string $procedureId;

    private Request $request;

    public function __construct(Request $request, string $procedureId)
    {
        $this->request      = $request;
        $this->procedureId  = $procedureId;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
    public function getProcedureId()
    {
        return $this->procedureId;
    }
}
