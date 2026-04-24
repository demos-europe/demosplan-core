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
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Random\Randomizer;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Throwable;

/**
 * Lifecycle manager for {@see PersonalAccessToken}: create, verify, revoke, list.
 *
 * Responsibilities:
 * - Generate the opaque secret portion and the lookup prefix
 * - Hash the secret with the application's password hasher (same factory the User entity uses)
 * - Enforce the invariants the entity itself can't: expiry bounded at +{@see PersonalAccessToken::MAX_LIFETIME_DAYS},
 *   non-empty scopes, scopes all present in {@see PersonalAccessTokenScope}
 * - Look tokens up by prefix in constant time (DB unique index) and verify in constant time (password_verify)
 *
 * The full token string exposed to the caller has the form
 *   dplan_pat_<12-char prefix><32-char secret>
 * The prefix + secret are URL-safe base32 characters (lowercase a-z2-7) to avoid = padding
 * and the visual ambiguity of 0/O/1/l.
 */
class PersonalAccessTokenService
{
    /**
     * RFC 4648 base32 alphabet without the padding/visually-ambiguous characters.
     * 32 symbols = 5 bits per char; 12-char prefix = 60 bits, 32-char secret = 160 bits of entropy.
     */
    private const TOKEN_ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';
    private const SECRET_LENGTH = 32;

    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
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

        $prefix = $this->generatePrefix();
        $secret = $this->generateSecret();
        $fullToken = PersonalAccessToken::TOKEN_LITERAL_PREFIX.$prefix.$secret;

        $hasher = $this->passwordHasherFactory->getPasswordHasher(PersonalAccessToken::class);
        $hash = $hasher->hash($secret);

        $token = new PersonalAccessToken(
            user: $user,
            customer: $customer,
            name: $name,
            tokenPrefix: $prefix,
            tokenHash: $hash,
            scopes: $validScopes,
            expiresAt: $expiresAt,
            procedureIds: $normalizedProcedureIds,
        );

        $this->repository->persistAndFlush($token);

        $this->logger->info('Personal access token created', [
            'token_prefix' => $prefix,
            'user_id'      => $user->getId(),
            'customer_id'  => $customer->getId(),
            'scopes'       => $validScopes,
            'expires_at'   => $expiresAt->format(DATE_ATOM),
        ]);

        $this->recordAuditEntry(
            static fn (PersonalAccessTokenReportEntryFactory $f) => $f->createCreationEntry($token),
            'create'
        );

        return new PersonalAccessTokenCreationResult($token, $fullToken);
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
        $parsed = $this->parse($fullToken);
        if (null === $parsed) {
            return null;
        }
        [$prefix, $secret] = $parsed;

        $token = $this->repository->findByPrefix($prefix);
        $hasher = $this->passwordHasherFactory->getPasswordHasher(PersonalAccessToken::class);

        if (null === $token) {
            $this->constantTimeNoop($hasher, $secret);

            return null;
        }

        if (!$hasher->verify($token->getTokenHash(), $secret)) {
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

    private function generatePrefix(): string
    {
        $randomizer = new Randomizer();
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $prefix = $randomizer->getBytesFromString(self::TOKEN_ALPHABET, PersonalAccessToken::TOKEN_PREFIX_LENGTH);
            if (null === $this->repository->findByPrefix($prefix)) {
                return $prefix;
            }
        }
        throw new \RuntimeException('Failed to generate a unique PAT prefix after 5 attempts.');
    }

    private function generateSecret(): string
    {
        return (new Randomizer())->getBytesFromString(self::TOKEN_ALPHABET, self::SECRET_LENGTH);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parse(string $fullToken): ?array
    {
        $literal = PersonalAccessToken::TOKEN_LITERAL_PREFIX;
        if (!str_starts_with($fullToken, $literal)) {
            return null;
        }
        $body = substr($fullToken, strlen($literal));
        $expectedLength = PersonalAccessToken::TOKEN_PREFIX_LENGTH + self::SECRET_LENGTH;
        if (strlen($body) !== $expectedLength) {
            return null;
        }
        $prefix = substr($body, 0, PersonalAccessToken::TOKEN_PREFIX_LENGTH);
        $secret = substr($body, PersonalAccessToken::TOKEN_PREFIX_LENGTH);

        return [$prefix, $secret];
    }

    /**
     * Runs a hash verification against a known-bad value to keep timing uniform when
     * the prefix lookup misses. The result is discarded.
     */
    private function constantTimeNoop(PasswordHasherInterface $hasher, string $secret): void
    {
        static $dummyHash = null;
        if (null === $dummyHash) {
            $dummyHash = $hasher->hash(bin2hex(random_bytes(16)));
        }
        $hasher->verify($dummyHash, $secret);
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
