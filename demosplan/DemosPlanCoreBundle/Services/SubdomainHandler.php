<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
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

    public function __construct(
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        private readonly CustomerRepository $customerRepository
    ) {
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

        try {
            $customer = $this->customerRepository->findCustomerBySubdomain($urlSubdomain);
            $this->logger->debug('Customer found', [$customer->getSubdomain()]);

            return $urlSubdomain;
        } catch (\Exception $e) {
            $this->logger->info('Customer not found, using default customer', [$e->getMessage()]);
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
