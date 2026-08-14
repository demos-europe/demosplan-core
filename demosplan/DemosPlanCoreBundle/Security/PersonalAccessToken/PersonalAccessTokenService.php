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

use DateInterval;
use DateTime;
use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Report\PersonalAccessTokenReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\PersonalAccessTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Lifecycle manager for {@see PersonalAccessToken}: create, verify, revoke, list.
 *
 * Owns what is specific to a user-owned token — the invariants the entity itself can't enforce
 * (expiry bounded at +{@see PersonalAccessToken::MAX_LIFETIME_DAYS}, non-empty scopes, scopes all
 * present in {@see PersonalAccessTokenScope}), the audit trail, and revocation. Secret generation,
 * hashing, verification and parsing live in {@see ApiTokenSecretService}, shared with every other
 * token kind.
 *
 * The full token string exposed to the caller has the form
 *   dplan_pat_<12-char prefix><32-char secret>
 */
class PersonalAccessTokenService
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiTokenSecretService $secretService,
        private readonly PersonalAccessTokenReportEntryFactory $reportEntryFactory,
        private readonly ReportService $reportService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Creates and persists a new token. Returns the plaintext secret alongside the entity;
     * the plaintext is never persisted and cannot be recovered later — the caller is
     * responsible for surfacing it to the user exactly once.
     *
     * @param list<string>      $scopes       Non-empty list; each entry must be a known scope
     * @param list<string>|null $procedureIds Optional procedure-id allowlist
     */
    public function create(
        User $user,
        Customer $customer,
        string $name,
        array $scopes,
        DateTime $expiresAt,
        ?array $procedureIds = null,
    ): PersonalAccessTokenCreationResult {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('PAT name must not be empty.');
        }
        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('PAT name exceeds 120 characters.');
        }

        $validScopes = PersonalAccessTokenScope::filterValid($scopes);
        if ([] === $validScopes) {
            throw new InvalidArgumentException('At least one valid scope is required.');
        }

        $now = new DateTimeImmutable();
        if ($expiresAt <= DateTime::createFromImmutable($now)) {
            throw new InvalidArgumentException('PAT expiry must be in the future.');
        }
        $maxExpiry = $now->add(new DateInterval('P'.PersonalAccessToken::MAX_LIFETIME_DAYS.'D'));
        if ($expiresAt > DateTime::createFromImmutable($maxExpiry)) {
            throw new InvalidArgumentException(sprintf(
                'PAT expiry may not exceed +%d days.',
                PersonalAccessToken::MAX_LIFETIME_DAYS
            ));
        }

        $normalizedProcedureIds = null;
        if (null !== $procedureIds && [] !== $procedureIds) {
            $normalizedProcedureIds = array_values(array_unique(array_filter(
                $procedureIds,
                static fn ($id) => is_string($id) && '' !== trim($id)
            )));
            if ([] === $normalizedProcedureIds) {
                $normalizedProcedureIds = null;
            }
        }

        $generated = $this->secretService->generate(
            PersonalAccessToken::TOKEN_LITERAL_PREFIX,
            fn (string $prefix): bool => null !== $this->repository->findByPrefix($prefix),
        );

        $token = new PersonalAccessToken(
            user: $user,
            customer: $customer,
            name: $name,
            tokenPrefix: $generated->prefix,
            tokenHash: $generated->hash,
            scopes: $validScopes,
            expiresAt: $expiresAt,
            procedureIds: $normalizedProcedureIds,
        );

        $this->repository->persistAndFlush($token);

        $this->logger->info('Personal access token created', [
            'token_prefix' => $generated->prefix,
            'user_id'      => $user->getId(),
            'customer_id'  => $customer->getId(),
            'scopes'       => $validScopes,
            'expires_at'   => $expiresAt->format(DATE_ATOM),
        ]);

        $this->recordAuditEntry(
            static fn (PersonalAccessTokenReportEntryFactory $f) => $f->createCreationEntry($token),
            'create'
        );

        return new PersonalAccessTokenCreationResult($token, $generated->fullToken);
    }

    /**
     * Parses a full token string and looks up the matching entity if the secret verifies.
     * Returns null for any malformed, unknown, revoked, or expired token.
     *
     * Timing: looks up by prefix first (indexed), then performs a constant-time hash
     * verification. A constant-time dummy hash is verified when the prefix is unknown so
     * that the observable response time does not depend on whether the prefix exists.
     */
    public function findByPlaintext(string $fullToken): ?PersonalAccessToken
    {
        $parsed = $this->secretService->parse(PersonalAccessToken::TOKEN_LITERAL_PREFIX, $fullToken);
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

        return $token->isActive(new DateTime()) ? $token : null;
    }

    public function markUsed(PersonalAccessToken $token): void
    {
        $token->markUsed(new DateTime());
        $this->entityManager->flush();
    }

    public function revoke(PersonalAccessToken $token, ?User $by = null): void
    {
        if ($token->isRevoked()) {
            return;
        }
        $token->revoke(new DateTime(), $by);
        $this->entityManager->flush();

        $this->logger->info('Personal access token revoked', [
            'token_prefix' => $token->getTokenPrefix(),
            'user_id'      => $token->getUser()->getId(),
            'revoked_by'   => $by?->getId(),
        ]);

        $this->recordAuditEntry(
            static fn (PersonalAccessTokenReportEntryFactory $f) => $f->createRevocationEntry($token, $by),
            'revoke'
        );
    }

    /**
     * @return list<PersonalAccessToken>
     */
    public function listForUser(User $user): array
    {
        return $this->repository->findAllByUser($user);
    }

    public function revokeAllForUser(User $user, ?User $by = null): int
    {
        return $this->repository->revokeAllForUser($user, new DateTime(), $by);
    }

    /**
     * Writes an audit entry best-effort: a failure to record (e.g. no current customer
     * context in a CLI job) must not block the primary token operation.
     *
     * @param callable(PersonalAccessTokenReportEntryFactory): \demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry $build
     */
    private function recordAuditEntry(callable $build, string $operation): void
    {
        try {
            $entry = $build($this->reportEntryFactory);
            $this->reportService->persistAndFlushReportEntry($entry);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to record PAT audit entry', [
                'operation' => $operation,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
