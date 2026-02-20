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
 * Handles encryption and decryption of OAuth tokens using Sodium (AES-256-GCM).
 *
 * This service provides authenticated encryption for storing sensitive OAuth tokens
 * in the database, ensuring confidentiality and integrity of the stored data.
 */
class TokenEncryptionService
{
    /**
     * Length of nonce required by AES-256-GCM (12 bytes).
     */
    private const NONCE_LENGTH = SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES;

    /**
     * The encryption key used for AES-256-GCM.
     * Must be 32 bytes (256 bits) for AES-256.
     */
    private readonly string $encryptionKey;

    public function __construct(
        private readonly LoggerInterface $logger,
        string $encryptionKey
    ) {
        // Validate AES-256-GCM is available on this system
        if (!sodium_crypto_aead_aes256gcm_is_available()) {
            throw new TokenEncryptionException('AES-256-GCM is not available on this system. Hardware support required.');
        }

        // Validate encryption key is provided
        if ('' === $encryptionKey) {
            throw new TokenEncryptionException('OAuth token encryption key not configured. Set OAUTH_TOKEN_ENCRYPTION_KEY in your .env');
        }

        // Validate and decode hex key
        if (!ctype_xdigit($encryptionKey)) {
            throw new TokenEncryptionException('OAuth token encryption key must be a valid hexadecimal string');
        }

        $key = hex2bin($encryptionKey);

        if (false === $key || SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES !== strlen($key)) {
            throw new TokenEncryptionException(
                sprintf(
                    'OAuth token encryption key must be exactly %d bytes (64 hex characters)',
                    SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES
                )
            );
        }

        $this->encryptionKey = $key;
    }

    /**
     * Encrypts plaintext using AES-256-GCM with authentication.
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
            // Generate a random nonce (number used once)
            $nonce = random_bytes(self::NONCE_LENGTH);

            // Encrypt with authenticated encryption (AEAD)
            // No additional data (AD) is used in this implementation
            $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
                $plaintext,
                '',  // No additional authenticated data
                $nonce,
                $this->encryptionKey
            );

            // Prepend nonce to ciphertext and encode as base64 for storage
            $encrypted = base64_encode($nonce.$ciphertext);

            // Clear sensitive data from memory
            sodium_memzero($plaintext);

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

            if (strlen($nonce) !== self::NONCE_LENGTH) {
                throw new TokenEncryptionException('Invalid nonce length in encrypted data');
            }

            // Decrypt with authentication verification
            $plaintext = sodium_crypto_aead_aes256gcm_decrypt(
                $ciphertext,
                '',  // No additional authenticated data was used
                $nonce,
                $this->encryptionKey
            );

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
