<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractApiTest extends FunctionalTestCase
{
    /**
     * @var KernelBrowser
     */
    protected $client;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var JWTTokenManagerInterface
     */
    protected $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();

        $serverParameters = $this->getServerParameters();

        $this->client = $this->getContainer()->get('test.client');
        $this->client->setServerParameters($serverParameters);

        $this->router = $this->getContainer()->get(RouterInterface::class);
        $this->tokenManager = $this->getContainer()->get(JWTTokenManagerInterface::class);
    }

    /**
     * @return string the JWT token to authenticate in API requests as the given user
     */
    protected function initializeUser(User $user): string
    {
        $token = $this->tokenManager->create($user);
        $userToken = new JWTUserToken($user->getRoleCodes(), $user, $token);
        $this->tokenStorage->setToken($userToken);

        return $token;
    }

    protected function sendRequest(string $urlPath, string $method, User $user, ?Procedure $procedure, array $requestBody = []): Response
    {
        $jwtToken = $this->initializeUser($user);
        $headers = $this->getAdditionalHeaders($jwtToken, $procedure);
        $content = [] === $requestBody
            ? null
            : Json::encode($requestBody);

        $this->client->request($method, $urlPath, [], [], $headers, $content);

        return $this->client->getResponse();
    }

    /**
     * @return array<string, string>
     */
    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        $headers = [
            'HTTP_X-JWT-Authorization' => "Bearer $jwtToken",
        ];
        if (null !== $procedure) {
            $headers['HTTP_X_DEMOSPLAN_PROCEDURE_ID'] = $procedure->getId();
        }

        return $headers;
    }

    abstract protected function getServerParameters(): array;
}
