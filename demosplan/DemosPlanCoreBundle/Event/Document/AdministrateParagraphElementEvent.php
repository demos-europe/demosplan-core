<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Document;

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Event to allow custom actions on paragraph administration.
 *
 * Class AdministrateParagraphElementEvent
 */
class AdministrateParagraphElementEvent extends DPlanEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @param string $procedureId
     * @param string $elementId
     */
    public function __construct(Request $request, protected $procedureId, protected $elementId)
    {
        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getProcedureId()
    {
        return $this->procedureId;
    }

    /**
     * @return string
     */
    public function getElementId()
    {
        return $this->elementId;
    }
}
