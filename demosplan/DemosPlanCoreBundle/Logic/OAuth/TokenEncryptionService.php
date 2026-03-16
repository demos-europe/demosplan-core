<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OAuth;

use demosplan\DemosPlanCoreBundle\Exception\TokenEncryptionException;
use Exception;
use Psr\Log\LoggerInterface;
use SodiumException;

/**
 * Handles encryption and decryption of OAuth tokens using Sodium (XSalsa20-Poly1305).
 *
 * This service provides authenticated encryption for storing sensitive OAuth tokens
 * in the database, ensuring confidentiality and integrity of the stored data.
 */
class TokenEncryptionService
{
    /**
     * Length of nonce required by secretbox (24 bytes).
     */
    private const NONCE_LENGTH = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    /**
     * The encryption key used for XSalsa20-Poly1305.
     * Must be 32 bytes (256 bits).
     */
    private readonly string $encryptionKey;

    public function __construct(
        private readonly LoggerInterface $logger,
        string $encryptionKey,
    ) {
        // Validate encryption key is provided
        if ('' === $encryptionKey) {
            throw new TokenEncryptionException('OAuth token encryption key not configured. Set OAUTH_TOKEN_ENCRYPTION_KEY in your .env');
        }

        // Validate and decode hex key
        if (!ctype_xdigit($encryptionKey)) {
            throw new TokenEncryptionException('OAuth token encryption key must be a valid hexadecimal string');
        }

        $key = hex2bin($encryptionKey);

        if (false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($key)) {
            throw new TokenEncryptionException(sprintf('OAuth token encryption key must be exactly %d bytes (64 hex characters)', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        $this->encryptionKey = $key;
    }

    /**
     * Encrypts plaintext using XSalsa20-Poly1305 with authentication.
     *
     * @param string $plaintext The data to encrypt
     *
     * @return string Base64-encoded string containing nonce + ciphertext
     *
     * @throws TokenEncryptionException if encryption fails
     */
    public function encrypt(string $plaintext): string
    {
        try {
            // Generate a random nonce (number used once) — 24 bytes for XSalsa20
            $nonce = random_bytes(self::NONCE_LENGTH);

            // Encrypt with authenticated encryption (XSalsa20-Poly1305)
            // Poly1305 MAC provides tamper detection without additional data
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->encryptionKey);

            // Prepend nonce to ciphertext and encode as base64 for storage
            $encrypted = base64_encode($nonce.$ciphertext);

            return $encrypted;
        } catch (Exception $e) {
            $this->logger->error('Failed to encrypt OAuth token', [
                'exception' => $e->getMessage(),
            ]);

            throw new TokenEncryptionException('Token encryption failed', 0, $e);
        }
    }

    /**
     * Decrypts data that was encrypted with encrypt().
     *
     * @param string $encryptedData Base64-encoded string containing nonce + ciphertext
     *
     * @return string The decrypted plaintext
     *
     * @throws TokenEncryptionException if decryption fails or data is tampered with
     */
    public function decrypt(string $encryptedData): string
    {
        try {
            // Decode base64
            $decoded = base64_decode($encryptedData, true);

            if (false === $decoded) {
                throw new TokenEncryptionException('Invalid base64-encoded encrypted data');
            }

            // Extract nonce and ciphertext
            $nonce = substr($decoded, 0, self::NONCE_LENGTH);
            $ciphertext = substr($decoded, self::NONCE_LENGTH);

            if (self::NONCE_LENGTH !== strlen($nonce)) {
                throw new TokenEncryptionException('Invalid nonce length in encrypted data');
            }

            // Decrypt with authentication verification — false indicates tampered or corrupt data
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey);

            if (false === $plaintext) {
                throw new TokenEncryptionException('Decryption failed - data may be corrupted or tampered with');
            }

            return $plaintext;
        } catch (SodiumException $e) {
            $this->logger->error('Failed to decrypt OAuth token', [
                'exception' => $e->getMessage(),
            ]);

            throw new TokenEncryptionException('Token decryption failed', 0, $e);
        }
    }
}
