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

use demosplan\DemosPlanCoreBundle\Doctrine\Type\EncryptedStringType;
use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class EncryptedStringTypeTest extends TestCase
{
    private ?EncryptedStringType $sut = null;
    private ?SecretEncryptor $encryptor = null;
    private ?AbstractPlatform $platform = null;

    protected function setUp(): void
    {
        $this->encryptor = new SecretEncryptor(base64_encode(sodium_crypto_secretbox_keygen()));
        EncryptedStringType::setEncryptor($this->encryptor);
        $this->sut = new EncryptedStringType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    protected function tearDown(): void
    {
        EncryptedStringType::setLogger(new NullLogger());
    }

    public function testDecryptsStoredValueWithoutWarning(): void
    {
        $logger = $this->createLoggerMock();
        $logger->expects(self::never())->method('warning');
        EncryptedStringType::setLogger($logger);

        $stored = $this->encryptor->encrypt('client-secret');

        self::assertSame('client-secret', $this->sut->convertToPHPValue($stored, $this->platform));
    }

    public function testLogsWarningAndReturnsStoredValueWhenDecryptionFails(): void
    {
        $logger = $this->createLoggerMock();
        $logger->expects(self::once())->method('warning');
        EncryptedStringType::setLogger($logger);

        $foreignCiphertext = (new SecretEncryptor(base64_encode(sodium_crypto_secretbox_keygen())))->encrypt('client-secret');

        self::assertSame($foreignCiphertext, $this->sut->convertToPHPValue($foreignCiphertext, $this->platform));
    }

    /**
     * @return LoggerInterface&MockObject
     */
    private function createLoggerMock(): LoggerInterface
    {
        return $this->createMock(LoggerInterface::class);
    }
}
