<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\ApiToken;

use demosplan\DemosPlanCoreBundle\Entity\User\AbstractApiToken;
use Random\Randomizer;
use RuntimeException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * The secret half of every API token: generation, hashing, constant-time verification and parsing.
 * The per-kind services own the entity, its invariants and its audit trail, so a new token kind
 * brings no new crypto code and needs no further `password_hashers` entry.
 */
class ApiTokenSecretService
{
    /**
     * RFC 4648 base32 alphabet without the padding/visually-ambiguous characters.
     * 32 symbols = 5 bits per char; 12-char prefix = 60 bits, 32-char secret = 160 bits of entropy.
     */
    private const TOKEN_ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';
    private const SECRET_LENGTH = 32;
    private const PREFIX_ATTEMPTS = 5;

    private ?string $dummyHash = null;

    public function __construct(private readonly PasswordHasherFactoryInterface $passwordHasherFactory)
    {
    }

    /**
     * @param string                 $literalPrefix e.g. `dplan_pat_`
     * @param callable(string): bool $isPrefixTaken decides whether a candidate prefix is already in use
     */
    public function generate(string $literalPrefix, callable $isPrefixTaken): GeneratedApiToken
    {
        $randomizer = new Randomizer();
        $prefix = null;
        for ($attempt = 0; $attempt < self::PREFIX_ATTEMPTS; ++$attempt) {
            $candidate = $randomizer->getBytesFromString(
                self::TOKEN_ALPHABET,
                AbstractApiToken::TOKEN_PREFIX_LENGTH
            );
            if (!$isPrefixTaken($candidate)) {
                $prefix = $candidate;
                break;
            }
        }
        if (null === $prefix) {
            throw new RuntimeException(
                sprintf('Failed to generate a unique token prefix after %d attempts.', self::PREFIX_ATTEMPTS)
            );
        }

        $secret = $randomizer->getBytesFromString(self::TOKEN_ALPHABET, self::SECRET_LENGTH);

        return new GeneratedApiToken(
            prefix: $prefix,
            secret: $secret,
            hash: $this->hasher()->hash($secret),
            fullToken: $literalPrefix.$prefix.$secret,
        );
    }

    public function verify(string $storedHash, string $secret): bool
    {
        return $this->hasher()->verify($storedHash, $secret);
    }

    /**
     * A secret short enough for a human to read out or type, from the same unambiguous alphabet.
     */
    public function generateCode(int $length): string
    {
        if ($length < 1) {
            throw new RuntimeException('A code needs at least one character.');
        }

        return (new Randomizer())->getBytesFromString(self::TOKEN_ALPHABET, $length);
    }

    /**
     * Deterministic, so a value can be looked up by its digest. Unsalted and unstretched on purpose:
     * CSPRNG input leaves nothing but exhaustive search. Never use for anything a human chooses.
     */
    public function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Runs a verification against a known-bad value so that the observable response time does not
     * depend on whether the prefix lookup found a row. The result is discarded.
     */
    public function verifyAgainstMiss(string $secret): void
    {
        $hasher = $this->hasher();
        $this->dummyHash ??= $hasher->hash(bin2hex(random_bytes(16)));
        $hasher->verify($this->dummyHash, $secret);
    }

    /**
     * Splits a full token string into its prefix and secret, or returns null when it is not a
     * well-formed token of this kind.
     *
     * @return array{0: string, 1: string}|null
     */
    public function parse(string $literalPrefix, string $fullToken): ?array
    {
        if (!str_starts_with($fullToken, $literalPrefix)) {
            return null;
        }
        $body = substr($fullToken, strlen($literalPrefix));
        if (strlen($body) !== AbstractApiToken::TOKEN_PREFIX_LENGTH + self::SECRET_LENGTH) {
            return null;
        }

        return [
            substr($body, 0, AbstractApiToken::TOKEN_PREFIX_LENGTH),
            substr($body, AbstractApiToken::TOKEN_PREFIX_LENGTH),
        ];
    }

    public function secretLength(): int
    {
        return self::SECRET_LENGTH;
    }

    /**
     * Resolved for the base class so every token kind shares one `password_hashers` entry.
     */
    private function hasher(): PasswordHasherInterface
    {
        return $this->passwordHasherFactory->getPasswordHasher(AbstractApiToken::class);
    }
}
