<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use Symfony\Component\HttpFoundation\Response;

class PreRenderEvent extends DPlanEvent
{
    /**
     * @var string
     */
    protected $view;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A response instance
     */
    public function __construct($view, $parameters, $response)
    {
        $this->view = $view;
        $this->parameters = $parameters;
        $this->response = $response;
    }

    public function addParameter($key, $value)
    {
        if (!array_key_exists($key, $this->parameters)) {
            $this->parameters[$key] = $value;
        }
    }

    /**
     * @return mixed|string
     */
    public function getLocale()
    {
        $locale = '';
        if (array_key_exists('locale', $this->parameters)) {
            $locale = $this->parameters['locale'];
        }

        return $locale;
    }

    /**
     * @return string
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
