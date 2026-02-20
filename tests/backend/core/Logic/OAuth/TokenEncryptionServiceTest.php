<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\OAuth;

use demosplan\DemosPlanCoreBundle\Exception\TokenEncryptionException;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\TokenEncryptionService;
use Tests\Base\FunctionalTestCase;

class TokenEncryptionServiceTest extends FunctionalTestCase
{
    /** @var TokenEncryptionService */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        // Get logger from container
        $logger = $this->getContainer()->get('logger');

        // Get encryption key from container parameters
        $encryptionKey = $this->getContainer()->getParameter('oauth_token_encryption_key');

        // Manually instantiate service with dependencies
        $this->sut = new TokenEncryptionService($logger, $encryptionKey);
    }

    public function testEncryptAndDecryptRoundTrip(): void
    {
        // Arrange
        $plaintext = 'my-secret-access-token-12345';

        // Act
        $encrypted = $this->sut->encrypt($plaintext);
        $decrypted = $this->sut->decrypt($encrypted);

        // Assert
        self::assertNotEquals($plaintext, $encrypted);
        self::assertEquals($plaintext, $decrypted);
    }

    public function testEncryptProducesDifferentCiphertextsWithSamePlaintext(): void
    {
        // Arrange
        $plaintext = 'my-secret-access-token';

        // Act - encrypt the same plaintext twice
        $encrypted1 = $this->sut->encrypt($plaintext);
        $encrypted2 = $this->sut->encrypt($plaintext);

        // Assert - different nonces should produce different ciphertexts
        self::assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same plaintext
        self::assertEquals($plaintext, $this->sut->decrypt($encrypted1));
        self::assertEquals($plaintext, $this->sut->decrypt($encrypted2));
    }

    public function testEncryptWithEmptyString(): void
    {
        // Arrange
        $plaintext = '';

        // Act
        $encrypted = $this->sut->encrypt($plaintext);
        $decrypted = $this->sut->decrypt($encrypted);

        // Assert
        self::assertEquals($plaintext, $decrypted);
    }

    public function testEncryptWithLongToken(): void
    {
        // Arrange
        // Simulate a very long JWT token
        $plaintext = str_repeat('a', 10000);

        // Act
        $encrypted = $this->sut->encrypt($plaintext);
        $decrypted = $this->sut->decrypt($encrypted);

        // Assert
        self::assertEquals($plaintext, $decrypted);
        self::assertEquals(10000, strlen($decrypted));
    }

    public function testDecryptWithInvalidBase64ThrowsException(): void
    {
        // Arrange
        $invalidData = 'this-is-not-valid-base64!!!';

        // Assert
        $this->expectException(TokenEncryptionException::class);
        $this->expectExceptionMessage('Invalid base64-encoded encrypted data');

        // Act
        $this->sut->decrypt($invalidData);
    }

    public function testDecryptWithTamperedDataThrowsException(): void
    {
        // Arrange
        $plaintext = 'my-secret-token';
        $encrypted = $this->sut->encrypt($plaintext);

        // Tamper with the encrypted data
        $decoded = base64_decode($encrypted);
        $tampered = base64_encode(substr($decoded, 0, -5).'AAAAA'); // Replace last 5 bytes

        // Assert
        $this->expectException(TokenEncryptionException::class);
        $this->expectExceptionMessage('Decryption failed - data may be corrupted or tampered with');

        // Act
        $this->sut->decrypt($tampered);
    }

    public function testDecryptWithInvalidNonceLengthThrowsException(): void
    {
        // Arrange
        // Create data with invalid nonce length (only 5 bytes instead of 12)
        $invalidData = base64_encode(random_bytes(5));

        // Assert
        $this->expectException(TokenEncryptionException::class);
        $this->expectExceptionMessage('Invalid nonce length in encrypted data');

        // Act
        $this->sut->decrypt($invalidData);
    }

    public function testEncryptedDataIsBase64Encoded(): void
    {
        // Arrange
        $plaintext = 'test-token';

        // Act
        $encrypted = $this->sut->encrypt($plaintext);

        // Assert - should be valid base64
        self::assertIsString($encrypted);
        self::assertNotFalse(base64_decode($encrypted, true));
    }

    public function testMultipleEncryptDecryptOperations(): void
    {
        // Arrange
        $tokens = [
            'access-token-123',
            'refresh-token-456',
            'id-token-789',
        ];

        // Act & Assert
        foreach ($tokens as $token) {
            $encrypted = $this->sut->encrypt($token);
            $decrypted = $this->sut->decrypt($encrypted);
            self::assertEquals($token, $decrypted);
        }
    }

    public function testEncryptWithSpecialCharacters(): void
    {
        // Arrange
        $plaintext = "token-with-special-chars: üöä ß € \n\r\t";

        // Act
        $encrypted = $this->sut->encrypt($plaintext);
        $decrypted = $this->sut->decrypt($encrypted);

        // Assert
        self::assertEquals($plaintext, $decrypted);
    }
}
