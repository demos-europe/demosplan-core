<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\SetHttpTestPermissionsListener;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class HttpTestCase extends FunctionalTestCase
{
    public const JWT_AUTHORIZATION_HEADER = 'HTTP_X-JWT-Authorization';
    public const DEMOSPLAN_PROCEDURE_ID_HEADER = 'HTTP_X_DEMOSPLAN_PROCEDURE_ID';

    protected ?KernelBrowser $client;
    protected ?JWTManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        // the createClient() method cannot be used when kernel is booted
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->tokenManager = $this->getContainer()->get(JWTTokenManagerInterface::class);
    }

    /**
     * Override method to enable permissions via HTTP server param that
     * evaluated in {@see SetHttpTestPermissionsListener}.
     */
    protected function enablePermissions(array $permissionsToEnable): void
    {
        $this->client->setServerParameter(SetHttpTestPermissionsListener::X_DPLAN_TEST_PERMISSIONS, implode(',', $permissionsToEnable));
    }

    /**
     * This method is essential for simulating an authenticated user in tests.
     * Initializes a user for authentication by creating a JWT token,
     * setting it in a JWTUserToken, and then setting this token in the token storage.
     */
    protected function initializeUser(User $user): string
    {
        $token = $this->tokenManager->create($user);
        $userToken = new JWTUserToken($user->getDplanRolesArray(), $user, $token);
        $this->tokenStorage->setToken($userToken);

        return $token;
    }

    /**
     * Generates additional headers for HTTP requests, including JWT authorization and optional procedure ID.
     *
     * This method prepares the HTTP headers required for authenticated requests. It includes the JWT token for
     * authorization and, if provided, the procedure ID to specify the context of the request.
     */
    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        $headers[self::JWT_AUTHORIZATION_HEADER] = "Bearer $jwtToken";

        if (null !== $procedure) {
            $headers[self::DEMOSPLAN_PROCEDURE_ID_HEADER] = $procedure->getId();
        }

        return $headers;
    }
}
