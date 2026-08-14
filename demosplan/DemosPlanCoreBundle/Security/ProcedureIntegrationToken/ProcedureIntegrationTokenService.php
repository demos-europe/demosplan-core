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

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureIntegrationTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Resolves a presented integration token to its entity, and records its use.
 *
 * The pairing flow drives {@see self::create()}; the authentication path only ever reads.
 */
class ProcedureIntegrationTokenService
{
    public function __construct(
        private readonly ProcedureIntegrationTokenRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiTokenSecretService $secretService,
    ) {
    }

    /**
     * Issues a token for one procedure. The plaintext is returned once and never persisted.
     *
     * @param list<string> $scopes non-empty; each entry must be a known scope
     *
     * @throws InvalidArgumentException when the name or scopes are unusable, or the expiry is past
     */
    public function create(
        Procedure $procedure,
        Customer $customer,
        string $name,
        array $scopes,
        ?User $createdBy = null,
        ?DateTime $expiresAt = null,
    ): ProcedureIntegrationTokenCreationResult {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('Integration token name must not be empty.');
        }
        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Integration token name exceeds 120 characters.');
        }

        $validScopes = PersonalAccessTokenScope::filterValid($scopes);
        if ([] === $validScopes) {
            throw new InvalidArgumentException('At least one valid scope is required.');
        }

        // Null means "no expiry, revocation only", which is the norm for an integration; a date in the
        // past would silently produce a token that never works.
        if (null !== $expiresAt && $expiresAt <= new DateTime()) {
            throw new InvalidArgumentException('Integration token expiry must be in the future.');
        }

        $generated = $this->secretService->generate(
            ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX,
            fn (string $prefix): bool => null !== $this->repository->findByPrefix($prefix),
        );

        $token = new ProcedureIntegrationToken(
            procedure: $procedure,
            customer: $customer,
            name: $name,
            tokenPrefix: $generated->prefix,
            tokenHash: $generated->hash,
            scopes: $validScopes,
            createdBy: $createdBy,
            expiresAt: $expiresAt,
        );

        $this->repository->persistAndFlush($token);

        return new ProcedureIntegrationTokenCreationResult($token, $generated->fullToken);
    }

    public function revoke(ProcedureIntegrationToken $token, ?User $by = null): void
    {
        if ($token->isRevoked()) {
            return;
        }
        $token->revoke(new DateTime(), $by);
        $this->entityManager->flush();
    }

    /**
     * Returns null for any malformed, unknown, revoked or expired token, or one whose procedure has
     * been deleted. Timing does not reveal whether the prefix exists.
     */
    public function findByPlaintext(string $fullToken): ?ProcedureIntegrationToken
    {
        $parsed = $this->secretService->parse(
            ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX,
            $fullToken
        );
        if (null === $parsed) {
            return null;
        }
        [$prefix, $secret] = $parsed;

        $token = $this->repository->findByPrefix($prefix);
        if (null === $token) {
            $this->secretService->verifyAgainstMiss($secret);

            return null;
        }

        if (!$this->secretService->verify($token->getTokenHash(), $secret)) {
            return null;
        }

        if (!$token->isActive(new DateTime())) {
            return null;
        }

        // The procedure is the token's whole reason to exist; a deleted one leaves nothing to write to.
        return $token->getProcedure()->isDeleted() ? null : $token;
    }

    public function markUsed(ProcedureIntegrationToken $token): void
    {
        $token->markUsed(new DateTime());
        $this->entityManager->flush();
    }
}
