<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use Symfony\Component\HttpFoundation\Request;

interface SubdomainHandlerInterface
{
    public function setSubdomainParameter(Request $request): void;

    /**
     * Returns the url's subdomain if a customer could be found.
     * Otherwise returns the Config Parameter 'subdomain'.
     */
    public function getSubdomain(Request $request): string;

    public function getUrlSubdomain(Request $request): string;
}
