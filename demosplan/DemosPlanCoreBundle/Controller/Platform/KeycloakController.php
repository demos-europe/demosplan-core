<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use demosplan\DemosPlanCoreBundle\Logic\User\OzgKeycloakClientFactory;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class KeycloakController extends AbstractController
{
    /**
     * Link to this controller to start the "connect" process.
     */
    #[Route(path: '/connect/keycloak_ozg', name: 'connect_keycloak_ozg_start')]
    public function connect(OzgKeycloakClientFactory $ozgKeycloakClientFactory): RedirectResponse
    {
        // will redirect to keycloak!
        return $ozgKeycloakClientFactory
            ->createForCurrentCustomer()
            ->redirect(['openid'], []);
    }

    /**
     * After going to keycloak, you're redirected back here
     * because this is the "redirect_route" you configured
     * in config/packages/knpu_oauth2_client.yaml.
     */
    #[Route(path: '/connect/keycloak_ozg/check', name: 'connect_keycloak_ozg_check')]
    public function connectCheck(OzgKeycloakClientFactory $ozgKeycloakClientFactory): void
    {
        // ** if you want to *authenticate* the user, then
        // leave this method blank and create a Guard authenticator
    }

    #[Route(path: '/connect/keycloak', name: 'connect_keycloak_start')]
    public function connectKeycloak(ClientRegistry $clientRegistry): RedirectResponse
    {
        // will redirect to keycloak!
        return $clientRegistry
            ->getClient('keycloak') // key used in config/packages/knpu_oauth2_client.yaml
            ->redirect(['openid'], []);
    }

    /**
     * After going to keycloak, you're redirected back here
     * because this is the "redirect_route" you configured
     * in config/packages/knpu_oauth2_client.yaml.
     */
    #[Route(path: '/connect/keycloak/check', name: 'connect_keycloak_check')]
    public function connectKeycloakCheck(ClientRegistry $clientRegistry): void
    {
        // ** if you want to *authenticate* the user, then
        // leave this method blank and create an authenticator
    }
}
