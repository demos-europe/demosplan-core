<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Event to trigger Request validation.
 *
 * Class RequestValidationEvent
 */
class RequestValidationEvent extends DPlanEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response|RedirectResponse|null
     */
    protected $response;

    /**
     * @var string|null Scope to validate Request for. E.g. statementId
     */
    protected $scope;

    /**
     * @var mixed|null
     */
    protected $identifier;

    public function __construct(Request $request, Response $response = null, $scope = null, $identifier = null)
    {
        $this->request = $request;
        $this->response = $response;
        $this->scope = $scope;
        $this->identifier = $identifier;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Response|RedirectResponse|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return string|null
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @return mixed|null
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }
}
