<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Document;

use DemosEurope\DemosplanAddon\Contracts\Events\ElementsAdminListSaveEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use Symfony\Component\HttpFoundation\Request;

class ElementsAdminListSaveEvent extends DPlanEvent implements ElementsAdminListSaveEventInterface
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
