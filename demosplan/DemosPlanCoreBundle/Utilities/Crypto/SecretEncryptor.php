<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities\Crypto;

use demosplan\DemosPlanCoreBundle\Exception\CryptoException;
use SodiumException;

/**
 * Symmetric encryption/decryption of short secrets using libsodium's
 * crypto_secretbox (XSalsa20-Poly1305).
 *
 * The 32-byte key is expected as a base64-encoded environment variable.
 */
class SecretEncryptor
{
    private readonly string $key;

    public function __construct(string $oauthSecretEncryptionKey)
    {
        if ('' === $oauthSecretEncryptionKey) {
            throw new CryptoException('OAUTH_SECRET_ENCRYPTION_KEY must not be empty. Generate one with: php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"');
        }

        $decoded = base64_decode($oauthSecretEncryptionKey, true);
        if (false === $decoded || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($decoded)) {
            throw new CryptoException(sprintf('OAUTH_SECRET_ENCRYPTION_KEY must be a base64-encoded %d-byte key.', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        $this->key = $decoded;
    }

    /**
     * Encrypts a plaintext string and returns a base64-encoded nonce+ciphertext.
     *
     * @throws SodiumException
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce.$ciphertext);
    }

    /**
     * Decrypts a base64-encoded nonce+ciphertext back to plaintext.
     *
     * @throws CryptoException if decryption fails (wrong key or tampered data)
     */
    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if (false === $decoded) {
            throw new CryptoException('Failed to base64-decode encrypted value.');
        }

        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($decoded) < $nonceLength) {
            throw new CryptoException('Encrypted value is too short to contain a valid nonce.');
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $ciphertext = substr($decoded, $nonceLength);

        try {
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        } catch (SodiumException $e) {
            throw new CryptoException('Decryption failed: '.$e->getMessage(), 0, $e);
        }

        if (false === $plaintext) {
            throw new CryptoException('Decryption failed: wrong key or corrupted data.');
        }

        return $plaintext;
    }
}
