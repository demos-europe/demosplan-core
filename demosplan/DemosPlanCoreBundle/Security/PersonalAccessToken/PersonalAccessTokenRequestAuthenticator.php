<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken;

use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves a personal access token from the Authorization header to the owning {@see User} +
 * {@see PersonalAccessToken} pair, or null when the token is absent, malformed, revoked, expired or
 * unknown.
 *
 * A plain service, invoked from
 * {@see \demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\ApiAuthenticator};
 * returning null lets authentication fall through to JWT and then session. On success it attaches a
 * {@see PersonalAccessTokenContext} to the request for the permissions evaluator.
 */
class PersonalAccessTokenRequestAuthenticator
{
    public function __construct(
        private readonly PersonalAccessTokenService $tokenService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * True when the Authorization header carries something shaped like a personal access token, false
     * for anything else including JWT bearer tokens. Cheap enough to call on every request.
     */
    public function supports(Request $request): bool
    {
        return null !== $this->extractCandidate($request);
    }

    /**
     * Resolves the PAT from the request and returns the owning user on success.
     * On success, a {@see PersonalAccessTokenContext} is attached to the request.
     * Returns null without attaching anything when the token is invalid.
     */
    public function authenticate(Request $request): ?User
    {
        $candidate = $this->extractCandidate($request);
        if (null === $candidate) {
            return null;
        }

        $token = $this->tokenService->findByPlaintext($candidate);
        if (null === $token) {
            $this->logger->debug('PAT authentication failed: no active token matched', [
                'prefix_hint' => $this->extractPrefixHint($candidate),
            ]);

            return null;
        }

        $user = $token->getUser();
        if ($user->isDeleted()) {
            $this->logger->warning('PAT authentication rejected: owning user is deleted', [
                'token_prefix' => $token->getTokenPrefix(),
                'user_id'      => $user->getId(),
            ]);

            return null;
        }

        $this->tokenService->markUsed($token);

        $request->attributes->set(
            PersonalAccessTokenContext::REQUEST_ATTRIBUTE,
            PersonalAccessTokenContext::fromToken($token)
        );

        $this->logger->debug('API request authenticated via personal access token', [
            'token_prefix' => $token->getTokenPrefix(),
            'user_id'      => $user->getId(),
            'customer_id'  => $token->getCustomer()->getId(),
        ]);

        return $user;
    }

    /**
     * Extracts the raw token string if the Authorization header looks like a PAT.
     * Returns null for missing, malformed, or non-PAT bearer headers.
     */
    private function extractCandidate(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (null === $header) {
            return null;
        }
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $raw = substr($header, 7);
        if (!str_starts_with($raw, PersonalAccessToken::TOKEN_LITERAL_PREFIX)) {
            return null;
        }

        return $raw;
    }

    private function extractPrefixHint(string $fullToken): string
    {
        $literalLength = strlen(PersonalAccessToken::TOKEN_LITERAL_PREFIX);
        return substr($fullToken, $literalLength, PersonalAccessToken::TOKEN_PREFIX_LENGTH);
    }
}
