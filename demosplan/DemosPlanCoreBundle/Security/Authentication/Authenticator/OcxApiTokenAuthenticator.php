<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxApiToken;
use demosplan\DemosPlanCoreBundle\Exception\OcxProblemException;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiTokenService;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticates OCX senders by an {@see OcxApiToken}
 * sent as `Authorization: Bearer <token>`.
 */
class OcxApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    /**
     * Request attribute holding the id of the customer the API token was issued for.
     */
    public const CUSTOMER_ID_ATTRIBUTE = '_ocx_customer_id';

    public function __construct(private readonly OcxApiTokenService $tokenService)
    {
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractBearerToken($request);
        if (null === $token) {
            throw new CustomUserMessageAuthenticationException('Missing API token.');
        }

        $apiToken = $this->tokenService->findActiveToken($token);
        if (!$apiToken instanceof OcxApiToken) {
            throw new CustomUserMessageAuthenticationException('Invalid API token.');
        }
        $user = $apiToken->getUser();
        $request->attributes->set(self::CUSTOMER_ID_ATTRIBUTE, $apiToken->getCustomer()->getId());

        return new SelfValidatingPassport(new UserBadge($user->getLogin(), static fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $request->attributes->set(ApiTokenContextInterface::REQUEST_ATTRIBUTE, new OcxRequestContext());

        return null;
    }

    /**
     * @throws JsonException
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->unauthorized($exception->getMessageKey());
    }

    /**
     * @throws JsonException
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized('Authentication required.');
    }

    /**
     * @throws JsonException
     */
    private function unauthorized(string $detail): Response
    {
        $response = OcxProblemException::unauthorized($detail)->toResponse();
        $response->headers->set('WWW-Authenticate', 'Bearer');

        return $response;
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $key = trim(substr($header, 7));

        return '' === $key ? null : $key;
    }
}
