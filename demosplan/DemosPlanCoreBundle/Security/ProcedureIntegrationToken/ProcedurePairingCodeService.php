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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePairingCode;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePairingCodeRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Issues pairing codes and redeems them for durable integration tokens: one procedure, a few
 * minutes, exactly once.
 */
class ProcedurePairingCodeService
{
    /**
     * 12 characters over the 32-symbol alphabet: 60 bits, in three groups of four to read out.
     */
    public const CODE_LENGTH = 12;
    public const CODE_GROUP_SIZE = 4;
    public const DEFAULT_TTL_MINUTES = 15;
    private const MAX_TTL_MINUTES = 120;

    public function __construct(
        private readonly ProcedurePairingCodeRepository $repository,
        private readonly ProcedureIntegrationTokenService $tokenService,
        private readonly ApiTokenSecretService $secretService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $scopes scopes the exchanged token will carry
     *
     * @throws InvalidArgumentException when the name, scopes or lifetime are unusable
     */
    public function issue(
        Procedure $procedure,
        Customer $customer,
        string $name,
        array $scopes = [PersonalAccessTokenScope::RECOMMENDATIONS_WRITE],
        ?User $createdBy = null,
        int $ttlMinutes = self::DEFAULT_TTL_MINUTES,
    ): ProcedurePairingCodeIssueResult {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('Pairing code name must not be empty.');
        }
        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Pairing code name exceeds 120 characters.');
        }

        $validScopes = PersonalAccessTokenScope::filterValid($scopes);
        if ([] === $validScopes) {
            throw new InvalidArgumentException('At least one valid scope is required.');
        }

        if ($ttlMinutes < 1 || $ttlMinutes > self::MAX_TTL_MINUTES) {
            throw new InvalidArgumentException(
                sprintf('Pairing code lifetime must be between 1 and %d minutes.', self::MAX_TTL_MINUTES)
            );
        }

        $plaintext = $this->secretService->generateCode(self::CODE_LENGTH);

        $code = new ProcedurePairingCode(
            procedure: $procedure,
            customer: $customer,
            name: $name,
            codeHash: $this->secretService->digest($plaintext),
            scopes: $validScopes,
            expiresAt: new DateTime(sprintf('+%d minutes', $ttlMinutes)),
            createdBy: $createdBy,
        );

        $this->repository->persistAndFlush($code);

        $this->logger->info('Procedure pairing code issued', [
            'procedure_id' => $procedure->getId(),
            'code_id'      => $code->getId(),
            'expires_at'   => $code->getExpiresAt()->format(DATE_ATOM),
        ]);

        return new ProcedurePairingCodeIssueResult($code, $this->group($plaintext));
    }

    /**
     * One null for every kind of miss — unknown, expired, already redeemed, procedure gone — so the
     * unauthenticated endpoint in front of this cannot be used to learn whether a code existed.
     */
    public function redeem(string $presentedCode): ?ProcedureIntegrationTokenCreationResult
    {
        $normalized = $this->normalize($presentedCode);
        if (self::CODE_LENGTH !== strlen($normalized)) {
            return null;
        }

        $code = $this->repository->findByCodeHash($this->secretService->digest($normalized));
        if (null === $code) {
            return null;
        }

        $now = new DateTime();
        if (!$code->isRedeemable($now)) {
            $this->logger->info('Pairing code rejected', [
                'code_id'  => $code->getId(),
                'consumed' => $code->isConsumed(),
                'expired'  => $code->isExpired($now),
            ]);

            return null;
        }

        if ($code->getProcedure()->isDeleted()) {
            return null;
        }

        // Consume before issuing: a code spent by a failed exchange is safer than one that could be
        // redeemed twice.
        if (!$this->repository->consume($code, $now)) {
            $this->logger->warning('Pairing code lost the race to be consumed', [
                'code_id' => $code->getId(),
            ]);

            return null;
        }

        $result = $this->tokenService->create(
            procedure: $code->getProcedure(),
            customer: $code->getCustomer(),
            name: $code->getName(),
            scopes: $code->getScopes(),
            createdBy: $code->getCreatedBy(),
        );

        $this->logger->info('Pairing code exchanged for an integration token', [
            'code_id'      => $code->getId(),
            'procedure_id' => $code->getProcedure()->getId(),
            'token_prefix' => $result->token->getTokenPrefix(),
        ]);

        return $result;
    }

    /**
     * Accepts grouped or bare, any case, with stray whitespace.
     */
    private function normalize(string $presentedCode): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $presentedCode) ?? '');
    }

    private function group(string $code): string
    {
        return implode('-', str_split($code, self::CODE_GROUP_SIZE));
    }
}
