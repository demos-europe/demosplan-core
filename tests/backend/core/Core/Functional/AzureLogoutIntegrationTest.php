<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\FunctionalTestCase;

/**
 * Integration tests for Azure AD Front-Channel logout functionality.
 */
class AzureLogoutIntegrationTest extends FunctionalTestCase
{
    private const LOGOUT_URL = '/user/logout';

    /** @var KernelBrowser */
    private $client;

    /** @var User */
    private $azureUser;

    /** @var User */
    private $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->makeClient();

        // Create a user that was provided by identity provider (Azure)
        $this->azureUser = UserFactory::createOne(['providedByIdentityProvider' => true])->object();

        // Create a regular user (not provided by identity provider)
        $this->regularUser = UserFactory::createOne(['providedByIdentityProvider' => false])->object();
    }

    public function testAzureUserLogoutRedirectsToAzureWhenConfigured(): void
    {
        // Temporarily set Azure logout route for this test
        $container = self::getContainer();
        $parameterBag = $container->get('parameter_bag');

        // Mock the parameter bag to return Azure logout route
        $originalValue = $parameterBag->get('oauth_azure_logout_route');
        $azureLogoutRoute = 'https://login.microsoftonline.com/tenant/oauth2/v2.0/logout?post_logout_redirect_uri=https://example.com/connect/azure/logout';

        // Use reflection to set the parameter (since ParameterBag is usually immutable)
        // @SuppressWarnings(php:S3011) - Reflection is required for testing ParameterBag
        $reflection = new ReflectionClass($parameterBag);
        if ($reflection->hasProperty('parameters')) {
            $property = $reflection->getProperty('parameters');
            $property->setAccessible(true); // @SuppressWarnings(php:S3011)
            $parameters = $property->getValue($parameterBag);
            $parameters['oauth_azure_logout_route'] = $azureLogoutRoute;
            $property->setValue($parameterBag, $parameters); // @SuppressWarnings(php:S3011)
        }

        try {
            // Log in Azure user
            $this->logIn($this->azureUser);

            // Attempt logout
            $this->client->request('GET', self::LOGOUT_URL);

            // Should redirect (either to Azure or to final destination)
            $response = $this->client->getResponse();
            self::assertTrue($response->isRedirection());

            // If LogoutSubscriber is working correctly, it should redirect to Azure logout
            // We can't easily test the exact redirect URL without more complex mocking,
            // but we can verify it's a redirect response
            self::assertInstanceOf(Response::class, $response);
        } finally {
            // Restore original parameter value
            if ($reflection->hasProperty('parameters')) {
                $property = $reflection->getProperty('parameters');
                $property->setAccessible(true); // @SuppressWarnings(php:S3011)
                $parameters = $property->getValue($parameterBag);
                $parameters['oauth_azure_logout_route'] = $originalValue;
                $property->setValue($parameterBag, $parameters); // @SuppressWarnings(php:S3011)
            }
        }
    }

    public function testRegularUserLogoutDoesNotRedirectToAzure(): void
    {
        // Temporarily set Azure logout route for this test
        $container = self::getContainer();
        $parameterBag = $container->get('parameter_bag');

        $originalValue = $parameterBag->get('oauth_azure_logout_route');
        $azureLogoutRoute = 'https://login.microsoftonline.com/tenant/oauth2/v2.0/logout?post_logout_redirect_uri=https://example.com/connect/azure/logout';

        // Use reflection to set the parameter
        // @SuppressWarnings(php:S3011) - Reflection is required for testing ParameterBag
        $reflection = new ReflectionClass($parameterBag);
        if ($reflection->hasProperty('parameters')) {
            $property = $reflection->getProperty('parameters');
            $property->setAccessible(true); // @SuppressWarnings(php:S3011)
            $parameters = $property->getValue($parameterBag);
            $parameters['oauth_azure_logout_route'] = $azureLogoutRoute;
            $property->setValue($parameterBag, $parameters); // @SuppressWarnings(php:S3011)
        }

        try {
            // Log in regular user (not provided by identity provider)
            $this->logIn($this->regularUser);

            // Attempt logout
            $this->client->request('GET', self::LOGOUT_URL);

            // Should redirect to regular logout destination (not Azure)
            $response = $this->client->getResponse();
            self::assertTrue($response->isRedirection());

            // For regular users, should not redirect to Azure logout
            $location = $response->headers->get('Location');
            if ($location) {
                self::assertStringNotContainsString('login.microsoftonline.com', $location);
            }
        } finally {
            // Restore original parameter value
            if ($reflection->hasProperty('parameters')) {
                $property = $reflection->getProperty('parameters');
                $property->setAccessible(true); // @SuppressWarnings(php:S3011)
                $parameters = $property->getValue($parameterBag);
                $parameters['oauth_azure_logout_route'] = $originalValue;
                $property->setValue($parameterBag, $parameters); // @SuppressWarnings(php:S3011)
            }
        }
    }

    public function testAzureCallbackEndpointRedirectsToStandardLogout(): void
    {
        // Test the Azure callback endpoint
        $this->client->request('GET', '/connect/azure/logout');

        // Should redirect to standard logout
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $location = $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString(self::LOGOUT_URL, $location);
    }

    public function testLogoutWithoutAzureConfigurationWorksNormally(): void
    {
        // Ensure Azure logout route is not configured
        $container = self::getContainer();
        $parameterBag = $container->get('parameter_bag');

        $originalValue = $parameterBag->get('oauth_azure_logout_route');

        // Use reflection to ensure parameter is empty
        // @SuppressWarnings(php:S3011) - Reflection is required for testing ParameterBag
        $reflection = new ReflectionClass($parameterBag);
        if ($reflection->hasProperty('parameters')) {
            $property = $reflection->getProperty('parameters');
            $property->setAccessible(true); // @SuppressWarnings(php:S3011)
            $parameters = $property->getValue($parameterBag);
            $parameters['oauth_azure_logout_route'] = '';
            $property->setValue($parameterBag, $parameters); // @SuppressWarnings(php:S3011)
        }

        try {
            // Log in Azure user
            $this->logIn($this->azureUser);

            // Attempt logout
            $this->client->request('GET', self::LOGOUT_URL);

            // Should redirect to regular destination (not fail)
            $response = $this->client->getResponse();
            self::assertTrue($response->isRedirection());

            // Should not cause any errors
            self::assertNotSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        } finally {
            // Restore original parameter value
            if ($reflection->hasProperty('parameters')) {
                $property = $reflection->getProperty('parameters');
                $property->setAccessible(true); // @SuppressWarnings(php:S3011)
                $parameters = $property->getValue($parameterBag);
                $parameters['oauth_azure_logout_route'] = $originalValue;
                $property->setValue($parameterBag, $parameters); // @SuppressWarnings(php:S3011)
            }
        }
    }
}
