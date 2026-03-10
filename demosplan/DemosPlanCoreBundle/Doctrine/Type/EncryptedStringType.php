<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Doctrine\Type;

use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;
use RuntimeException;

/**
 * Doctrine DBAL type that transparently encrypts values before storing
 * and decrypts them when reading from the database.
 *
 * Uses SecretEncryptor (libsodium XSalsa20-Poly1305) under the hood.
 */
class EncryptedStringType extends StringType
{
    final public const DPLAN_ENCRYPTED_STRING = 'dplan.encrypted_string';

    private static ?SecretEncryptor $encryptor = null;

    /**
     * Called once by a compiler pass or service bootstrap to inject the encryptor.
     */
    public static function setEncryptor(SecretEncryptor $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::getEncryptor()->encrypt((string) $value);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        try {
            return self::getEncryptor()->decrypt((string) $value);
        } catch (RuntimeException) {
            // Legacy plaintext value not yet encrypted — return as-is.
            // It will be encrypted on next persist/flush.
            return (string) $value;
        }
    }

    public function getName(): string
    {
        return self::DPLAN_ENCRYPTED_STRING;
    }

    private static function getEncryptor(): SecretEncryptor
    {
        if (null === self::$encryptor) {
            throw new RuntimeException('EncryptedStringType requires a SecretEncryptor. Ensure the EncryptedStringTypeInitializer listener is registered.');
        }

        return self::$encryptor;
    }
}
