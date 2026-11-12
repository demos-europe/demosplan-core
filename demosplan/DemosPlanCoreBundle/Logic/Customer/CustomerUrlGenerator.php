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
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates absolute urls that point to the host of a given customer.
 *
 * Without a request (scheduled jobs, console commands) the router can only use the host of its
 * static context, which is the same for all customers. Links in mails created that way lead
 * to the wrong customer. Use this class whenever an absolute url has to be generated for a
 * specific customer outside of a request.
 *
 * The customer host is built from the context host by replacing its customer label, so the base
 * domain does not have to be configured. The parameter project_domain is only used if the
 * context host has no customer label (e.g. localhost).
 */
class CustomerUrlGenerator
{
    public function __construct(
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
        private readonly RouterInterface $router,
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
            return $this->router->generate($route, $parameters, RouterInterface::ABSOLUTE_URL);
        }

        $context->setHost($customerHost);
        try {
            return $this->router->generate($route, $parameters, RouterInterface::ABSOLUTE_URL);
        } finally {
            // the context is shared, do not leak the customer host into later url generation
            $context->setHost($originalHost);
        }
    }

    private function getCustomerHost(?CustomerInterface $customer, string $contextHost): ?string
    {
        $subdomain = $customer?->getSubdomain() ?? '';
        if ('' === $subdomain) {
            return null;
        }

        // Same rule as SubdomainHandler: a host with more than two labels carries the customer as
        // its first label, so only that label has to be replaced.
        $labels = explode('.', $contextHost);
        if (count($labels) > 2) {
            array_shift($labels);

            return $subdomain.'.'.implode('.', $labels);
        }

        $projectDomain = $this->globalConfig->getProjectDomain();
        if ('' !== $projectDomain) {
            return $subdomain.'.'.$projectDomain;
        }

        $this->logger->warning(
            'The context host has no customer part and project_domain is not set, prefixing the customer to the context host',
            ['customer' => $subdomain, 'contextHost' => $contextHost]
        );

        return $subdomain.'.'.$contextHost;
    }
}
