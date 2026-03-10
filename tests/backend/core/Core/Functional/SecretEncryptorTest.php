<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecretEncryptorTest extends TestCase
{
    private ?SecretEncryptor $sut = null;
    private ?string $validKey = null;

    protected function setUp(): void
    {
        $this->validKey = base64_encode(sodium_crypto_secretbox_keygen());
        $this->sut = new SecretEncryptor($this->validKey);
    }

    public function testEncryptAndDecryptRoundTrip(): void
    {
        $plaintext = 'super-secret-client-value';
        $encrypted = $this->sut->encrypt($plaintext);

        self::assertNotSame($plaintext, $encrypted);
        self::assertSame($plaintext, $this->sut->decrypt($encrypted));
    }

    public function testEncryptProducesDifferentCiphertextEachTime(): void
    {
        $plaintext = 'same-value';
        $a = $this->sut->encrypt($plaintext);
        $b = $this->sut->encrypt($plaintext);

        self::assertNotSame($a, $b, 'Each encryption should use a unique nonce');
        self::assertSame($plaintext, $this->sut->decrypt($a));
        self::assertSame($plaintext, $this->sut->decrypt($b));
    }

    public function testDecryptWithWrongKeyFails(): void
    {
        $encrypted = $this->sut->encrypt('secret');
        $otherKey = base64_encode(sodium_crypto_secretbox_keygen());
        $otherEncryptor = new SecretEncryptor($otherKey);

        $this->expectException(RuntimeException::class);
        $otherEncryptor->decrypt($encrypted);
    }

    public function testConstructorRejectsEmptyKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not be empty');
        new SecretEncryptor('');
    }

    public function testConstructorRejectsInvalidBase64(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('base64-encoded');
        new SecretEncryptor('not-valid-base64!!!');
    }

    public function testConstructorRejectsWrongKeyLength(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('base64-encoded');
        new SecretEncryptor(base64_encode('too-short'));
    }

    public function testDecryptRejectsTamperedData(): void
    {
        $encrypted = $this->sut->encrypt('secret');
        // Flip a character in the middle of the base64 string
        $tampered = substr($encrypted, 0, 10).'X'.substr($encrypted, 11);

        $this->expectException(RuntimeException::class);
        $this->sut->decrypt($tampered);
    }
}
