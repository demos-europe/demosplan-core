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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureIntegrationTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves a presented integration token to its entity, and records its use.
 *
 * Issuing and revoking belong to the pairing flow and are deliberately not here yet: authentication
 * is the only thing needed to serve a push, and keeping this class to that makes it obvious that the
 * authentication path never creates or mutates a credential.
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
