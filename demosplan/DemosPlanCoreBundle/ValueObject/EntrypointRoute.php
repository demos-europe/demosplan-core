<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * Class EntrypointForwardRoute.
 *
 * Hold the required configuration to do internal request forwarding
 *
 * @method void   setController(string $controller)
 * @method void   setRoute(string $controller)
 * @method void   setParameters(array $parameters)
 * @method void   setDoRedirect(bool $doRedirect)
 * @method string getController()
 * @method string getRoute()
 * @method array  getParameters()
 * @method bool   getDoRedirect()
 */
class EntrypointRoute extends ValueObject
{
    /**
     * @var string Controller in route forwarding notation, used for a forwarding config
     *
     * @see https://symfony.com/doc/3.4/controller/forwarding.html
     */
    protected $controller;

    /**
     * @var string Route name, used for a redirecting config
     */
    protected $route;

    /**
     * @var array Required parameters, can be empty
     */
    protected $parameters = [];

    /**
     * @var bool Should this be a redirect instead of a forward?
     */
    protected $doRedirect = false;

    public function redirectLeavesPlatform(): bool
    {
        return $this->doRedirect && str_starts_with($this->route, 'http');
    }
}
