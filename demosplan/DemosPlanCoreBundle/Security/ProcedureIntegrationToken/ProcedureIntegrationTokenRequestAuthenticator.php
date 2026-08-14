<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\ProcedureIntegrationUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sibling of {@see \demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenRequestAuthenticator}
 * for integration tokens, dispatching on the literal prefix so the two kinds never compete for a
 * request.
 *
 * Unlike a PAT there is no owning user to return: the token's subject is a procedure, so every
 * integration request runs as the same {@see ProcedureIntegrationUser}. What distinguishes one
 * token's rights from another's is the context attached to the request, not the principal.
 *
 * A plain service rather than a Symfony Authenticator, invoked from
 * {@see \demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\ApiAuthenticator}.
 */
class ProcedureIntegrationTokenRequestAuthenticator
{
    public function __construct(
        private readonly ProcedureIntegrationTokenService $tokenService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(Request $request): bool
    {
        return null !== $this->extractCandidate($request);
    }

    /**
     * On success attaches a {@see ProcedureIntegrationTokenContext} to the request and returns the
     * integration principal. Returns null without attaching anything when the token is invalid.
     */
    public function authenticate(Request $request): ?User
    {
        $candidate = $this->extractCandidate($request);
        if (null === $candidate) {
            return null;
        }

        $token = $this->tokenService->findByPlaintext($candidate);
        if (null === $token) {
            $this->logger->debug('Integration token authentication failed: no active token matched', [
                'prefix_hint' => $this->extractPrefixHint($candidate),
            ]);

            return null;
        }

        $this->tokenService->markUsed($token);

        $request->attributes->set(
            ProcedureIntegrationTokenContext::REQUEST_ATTRIBUTE,
            ProcedureIntegrationTokenContext::fromToken($token)
        );

        $this->logger->debug('API request authenticated via procedure integration token', [
            'token_prefix' => $token->getTokenPrefix(),
            'procedure_id' => $token->getProcedure()->getId(),
            'customer_id'  => $token->getCustomer()->getId(),
        ]);

        return new ProcedureIntegrationUser();
    }

    private function extractCandidate(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (null === $header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $raw = substr($header, 7);

        return str_starts_with($raw, ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX) ? $raw : null;
    }

    private function extractPrefixHint(string $fullToken): string
    {
        return substr(
            $fullToken,
            strlen(ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX),
            ProcedureIntegrationToken::TOKEN_PREFIX_LENGTH
        );
    }
}
