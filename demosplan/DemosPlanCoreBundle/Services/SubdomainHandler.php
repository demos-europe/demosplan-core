<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class SubdomainHandler implements SubdomainHandlerInterface
{
    /** @var GlobalConfigInterface */
    protected $globalConfig;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(GlobalConfigInterface $globalConfig, LoggerInterface $logger)
    {
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
    }

    public function setSubdomainParameter(Request $request): void
    {
        $this->getGlobalConfig()->setSubdomain($this->getSubdomain($request));
    }

    /**
     * Returns the url's subdomain if it exists and it is in allowedSubdomains' array.
     * Otherwise returns the Config Parameter 'subdomain'.
     */
    public function getSubdomain(Request $request): string
    {
        $urlSubdomain = $this->getUrlSubdomain($request);
        $this->logger->debug('Subdomain', [$urlSubdomain]);

        if (in_array($urlSubdomain, $this->globalConfig->getSubdomainsAllowed(), true)) {
            return $urlSubdomain;
        }

        return $this->getGlobalConfig()->getSubdomain();
    }

    public function getUrlSubdomain(Request $request): string
    {
        $requestHost = $request->getHost();
        $requestHost = $this->adjustHost($requestHost);
        $requestHostParts = explode('.', str_replace('www.', '', $requestHost));

        return count($requestHostParts) > 2 ? $requestHostParts[0] : '';
    }

    protected function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }

    /**
     * Adjust Host according to mapping from config.
     */
    private function adjustHost(string $requestHost): string
    {
        $this->logger->debug('Host (request)', [$requestHost]);
        $subdomainMap = $this->globalConfig->getSubdomainMap();
        foreach ($subdomainMap as $host => $mapping) {
            $requestHost = str_ireplace($host, $mapping, $requestHost);
        }

        return $requestHost;
    }
}
