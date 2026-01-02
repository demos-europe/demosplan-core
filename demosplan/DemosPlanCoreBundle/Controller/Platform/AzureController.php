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

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class AzureController extends AbstractController
{
    /**
     * Link to this controller to start the Azure OAuth "connect" process.
     */
    #[Route(path: '/connect/azure', name: 'connect_azure_start', options: ['expose' => true])]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Will redirect to Azure AD OAuth endpoint
        return $clientRegistry
            ->getClient('azure') // Key used in config/packages/knpu_oauth2_client.yaml
            ->redirect(['openid', 'profile', 'email'], []);
    }

    /**
     * After going to Azure AD, you're redirected back here
     * because this is the "redirect_route" you configured
     * in config/packages/knpu_oauth2_client.yaml.
     */
    #[Route(path: '/connect/azure/check', name: 'connect_azure_check')]
    public function connectCheck(ClientRegistry $clientRegistry): void
    {
        // ** if you want to *authenticate* the user, then
        // leave this method blank and create an authenticator
        // This is handled by AzureAuthenticator
    }

    /**
     * Front-Channel logout endpoint for Azure AD.
     * Azure AD will redirect here after logout, then we redirect to our standard logout.
     */
    #[Route(path: '/connect/azure/logout', name: 'connect_azure_logout', options: ['expose' => true])]
    public function logout(): RedirectResponse
    {
        // Redirect to standard DemosPlan logout route
        // The LogoutSubscriber will handle the actual logout process
        return $this->redirectToRoute('DemosPlan_user_logout');
    }
}
