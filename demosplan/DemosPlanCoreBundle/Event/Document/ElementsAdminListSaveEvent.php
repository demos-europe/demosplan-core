<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Document;

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use Symfony\Component\HttpFoundation\Request;

class ElementsAdminListSaveEvent extends DPlanEvent
{
    /** @var Request */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
