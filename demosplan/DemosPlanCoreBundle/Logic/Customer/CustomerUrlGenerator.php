<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Customer;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\CustomerUrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates absolute urls that point to the host of a given customer.
 *
 * Without a request (scheduled jobs, console commands) the router can only use the host of its
 * static context, which is the same for all customers. Links in mails created that way lead
 * to the wrong customer. Use this class whenever an absolute url has to be generated for a
 * specific customer outside of a request.
 *
 * The customer host is the customer subdomain prefixed to the parameter project_domain. Without
 * project_domain the customer label of the context host is replaced, which only works if the
 * context host itself is a customer host. Instances that serve all customers on one host set
 * customer_url_keep_context_host, their urls keep the context host.
 */
readonly class CustomerUrlGenerator implements CustomerUrlGeneratorInterface
{
    public function __construct(
        private GlobalConfigInterface $globalConfig,
        private LoggerInterface $logger,
        private RouterInterface $router,
        #[Autowire(param: 'customer_url_keep_context_host')]
        private bool $keepContextHost = false,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(?CustomerInterface $customer, string $route, array $parameters = []): string
    {
        $context = $this->router->getContext();
        $originalHost = $context->getHost();
        $customerHost = $this->getCustomerHost($customer, $originalHost);
        if (null === $customerHost) {
            return $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $context->setHost($customerHost);
        try {
            return $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            // the context is shared, do not leak the customer host into later url generation
            $context->setHost($originalHost);
        }
    }

    private function getCustomerHost(?CustomerInterface $customer, string $contextHost): ?string
    {
        $subdomain = $customer?->getSubdomain() ?? '';
        if ('' === $subdomain || $this->keepContextHost) {
            return null;
        }

        $projectDomain = $this->globalConfig->getProjectDomain();
        if ('' !== $projectDomain) {
            return $subdomain.'.'.$projectDomain;
        }

        // Same rule as SubdomainHandler: a host with more than two labels carries the customer as
        // its first label, so only that label has to be replaced.
        $labels = explode('.', $contextHost);
        if (count($labels) > 2) {
            array_shift($labels);

            return $subdomain.'.'.implode('.', $labels);
        }

        $this->logger->warning(
            'project_domain is not set and the context host has no customer part, prefixing the customer to the context host',
            ['customer' => $subdomain, 'contextHost' => $contextHost]
        );

        return $subdomain.'.'.$contextHost;
    }
}
