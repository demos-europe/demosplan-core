<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Services\CustomerServiceInterface;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Client\Provider\KeycloakClient;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Creates a per-customer Keycloak OAuth2 client based on database-stored configuration.
 *
 * Falls back to the static 'keycloak_ozg' client from knpu_oauth2_client.yaml
 * if no per-customer CustomerOAuthConfig record exists.
 */
class OzgKeycloakClientFactory
{
    public function __construct(
        private readonly CustomerServiceInterface $customerService,
        private readonly CustomerOAuthConfigRepository $configRepository,
        private readonly RequestStack $requestStack,
        private readonly ClientRegistry $clientRegistry,
        private readonly string $defaultClientName = 'keycloak_ozg',
    ) {
    }

    /**
     * Returns a KeycloakClient configured for the current customer.
     * Falls back to the static 'keycloak_ozg' client if no per-customer config exists.
     */
    public function createForCurrentCustomer(): OAuth2ClientInterface
    {
        $customer = $this->customerService->getCurrentCustomer();
        $config = $this->configRepository->findByCustomer($customer);

        if (null === $config) {
            return $this->clientRegistry->getClient($this->defaultClientName);
        }

        $provider = new Keycloak([
            'clientId'      => $config->getKeycloakClientId(),
            'clientSecret'  => $config->getKeycloakClientSecret(),
            'authServerUrl' => $config->getKeycloakAuthServerUrl(),
            'realm'         => $config->getKeycloakRealm(),
        ]);

        return new KeycloakClient($provider, $this->requestStack);
    }

    /**
     * Returns the per-customer Keycloak client ID for JWT role extraction,
     * or falls back to the provided global parameter value.
     */
    public function getClientIdForCurrentCustomer(string $globalFallback): string
    {
        $customer = $this->customerService->getCurrentCustomer();
        $config = $this->configRepository->findByCustomer($customer);

        return $config?->getKeycloakClientId() ?? $globalFallback;
    }
}
