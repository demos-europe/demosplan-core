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

use demosplan\DemosPlanCoreBundle\ValueObject\AzureUserData;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Tests\Base\FunctionalTestCase;

/**
 * Tests for simplified Azure OAuth user data for SCIM-provisioned users.
 */
class AzureUserDataTest extends FunctionalTestCase
{
    public const TEST_EMAIL = 'test@example.com';
    public const TEST_EXAMPLE_EMAIL = 'test.user@example.com';
    public const MISSING_EMAIL_MESSAGE = 'Email address is missing in Azure OAuth response';

    private ?AzureUserData $azureUserData;

    protected function setUp(): void
    {
        $this->azureUserData = new AzureUserData();
    }

    public function testBasicClaimsAreCorrectlyMappedFromResourceOwner(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email' => self::TEST_EMAIL,
                'sub'   => 'azure-user-id-123',
                'oid'   => 'object-id-456',
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals(self::TEST_EMAIL, $this->azureUserData->getEmailAddress());
        $this->assertEquals('azure-user-id-123', $this->azureUserData->getSubject());
        $this->assertEquals('object-id-456', $this->azureUserData->getObjectId());
    }

    public function testHandlesIncompleteResourceOwnerGracefully(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email' => self::TEST_EMAIL,
                // Missing sub and oid fields
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals(self::TEST_EMAIL, $this->azureUserData->getEmailAddress());
        $this->assertEquals('', $this->azureUserData->getSubject());
        $this->assertEquals('', $this->azureUserData->getObjectId());
    }

    public function testThrowsExceptionWhenEmailIsMissing(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'sub' => 'azure-user-id-123',
                'oid' => 'object-id-456',
                // Missing email
            ]);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage(self::MISSING_EMAIL_MESSAGE);

        $this->azureUserData->fill($resourceOwner);
    }

    public function testToStringIncludesAllFields(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email' => self::TEST_EMAIL,
                'sub'   => 'azure-user-id-123',
                'oid'   => 'object-id-456',
            ]);

        $this->azureUserData->fill($resourceOwner);

        $result = $this->azureUserData->__toString();

        $this->assertStringContainsString('emailAddress: '.self::TEST_EMAIL, $result);
        $this->assertStringContainsString('objectId: object-id-456', $result);
        $this->assertStringContainsString('subject: azure-user-id-123', $result);
    }

    public function testEmailExtractedFromUpnFieldWhenEmailMissing(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'upn' => 'TS00626@c-ssi-test.de',
                'sub' => 'azure-user-id-123',
                'oid' => 'object-id-456',
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals('TS00626@c-ssi-test.de', $this->azureUserData->getEmailAddress());
        $this->assertEquals('azure-user-id-123', $this->azureUserData->getSubject());
        $this->assertEquals('object-id-456', $this->azureUserData->getObjectId());
    }

    public function testEmailExtractedFromUniqueNameFieldWhenEmailAndUpnMissing(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'unique_name' => 'user@domain.com',
                'sub'         => 'azure-user-id-123',
                'oid'         => 'object-id-456',
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals('user@domain.com', $this->azureUserData->getEmailAddress());
        $this->assertEquals('azure-user-id-123', $this->azureUserData->getSubject());
        $this->assertEquals('object-id-456', $this->azureUserData->getObjectId());
    }

    public function testEmailFieldTakesPriorityOverUpnAndUniqueName(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'       => 'primary@email.com',
                'upn'         => 'fallback@upn.com',
                'unique_name' => 'fallback@unique.com',
                'sub'         => 'azure-user-id-123',
                'oid'         => 'object-id-456',
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals('primary@email.com', $this->azureUserData->getEmailAddress());
    }

    public function testUpnFieldTakesPriorityOverUniqueName(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'upn'         => 'upn@email.com',
                'unique_name' => 'unique@email.com',
                'sub'         => 'azure-user-id-123',
                'oid'         => 'object-id-456',
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals('upn@email.com', $this->azureUserData->getEmailAddress());
    }

    public function testThrowsExceptionWhenAllEmailFieldsAreMissingFromCompleteAzureResponse(): void
    {
        // Test with a complete Azure OAuth response that has many fields but no email fields
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'aud'         => 'https://graph.windows.net/',
                'iss'         => 'https://sts.windows.net/tenant-id/',
                'iat'         => 1600000000,
                'nbf'         => 1600000000,
                'exp'         => 1600003600,
                'family_name' => 'Doe',
                'given_name'  => 'John',
                'name'        => 'Doe, John',
                'sub'         => 'azure-user-id-123',
                'oid'         => 'object-id-456',
                'tid'         => 'tenant-id',
                'roles'       => ['User'],
                // No email, upn, or unique_name fields in complete response
            ]);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage(self::MISSING_EMAIL_MESSAGE);

        $this->azureUserData->fill($resourceOwner);
    }

    public function testThrowsExceptionWhenAllEmailFieldsAreEmptyStrings(): void
    {
        // Test with empty strings instead of missing fields (different from testThrowsExceptionWhenEmailIsMissing)
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'            => '',
                'upn'              => '',
                'unique_name'      => '',
                'sub'              => 'azure-user-id-123',
                'oid'              => 'object-id-456',
                'additional_field' => 'some-value', // Additional field to differentiate from missing fields test
            ]);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage(self::MISSING_EMAIL_MESSAGE);

        $this->azureUserData->fill($resourceOwner);
    }

    public function testHandlesTypicalAzureTokenDataStructure(): void
    {
        // This test case simulates typical Azure OAuth response structure
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'aud'         => 'https://graph.windows.net/',
                'iss'         => 'https://sts.windows.net/example-tenant-id/',
                'iat'         => 1600000000,
                'nbf'         => 1600000000,
                'exp'         => 1600003600,
                'family_name' => 'Doe',
                'given_name'  => 'John',
                'name'        => 'Doe, John (extern)',
                'oid'         => 'example-object-id-123',
                'sub'         => 'example-subject-id-456',
                'unique_name' => self::TEST_EXAMPLE_EMAIL,
                'upn'         => self::TEST_EXAMPLE_EMAIL,
                'tid'         => 'example-tenant-id',
                // Note: no 'email' field present - typical for some Azure configurations
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals(self::TEST_EXAMPLE_EMAIL, $this->azureUserData->getEmailAddress());
        $this->assertEquals('example-subject-id-456', $this->azureUserData->getSubject());
        $this->assertEquals('example-object-id-123', $this->azureUserData->getObjectId());
    }

    public function testHandlesEntraIdExternalGuestUserIdToken(): void
    {
        // Realistic id_token structure from an EntraID external guest user (EXT).
        // The Azure OAuth library uses id_token claims (not access_token claims).
        // The id_token only has 'preferred_username' — no 'email', 'upn', or 'unique_name'.
        // Email must be resolved from the 'preferred_username' fallback.
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'aud'                => 'aabbccdd-1234-5678-abcd-ef0123456789',
                'iss'                => 'https://login.microsoftonline.com/11223344-aabb-ccdd-eeff-556677889900/v2.0',
                'iat'                => 1700000000,
                'nbf'                => 1700000000,
                'exp'                => 1700003600,
                'name'               => 'External User (EXT)',
                'oid'                => 'oid-ext-guest-1234-5678-abcdef012345',
                'preferred_username' => 'external.user@guest-tenant.onmicrosoft.com',
                'sid'                => 'session-id-1234-5678-abcdef012345',
                'sub'                => 'sub-id-ext-guest-abcdefghijklmnopqrstuvwxyz',
                'tid'                => '11223344-aabb-ccdd-eeff-556677889900',
                'uti'                => 'uti-placeholder-value',
                'ver'                => '2.0',
                // No 'email', 'upn', or 'unique_name' — only 'preferred_username'
            ]);

        $this->azureUserData->fill($resourceOwner);

        $this->assertEquals(
            'external.user@guest-tenant.onmicrosoft.com',
            $this->azureUserData->getEmailAddress(),
            'Email should be resolved from preferred_username when email/upn/unique_name are missing in id_token'
        );
        $this->assertEquals('oid-ext-guest-1234-5678-abcdef012345', $this->azureUserData->getObjectId());
        $this->assertEquals('sub-id-ext-guest-abcdefghijklmnopqrstuvwxyz', $this->azureUserData->getSubject());
        $this->assertEquals('External', $this->azureUserData->getFirstName());
        $this->assertEquals('User (EXT)', $this->azureUserData->getLastName());
    }

    public function testThrowsExceptionForEntraIdExternalGuestUserIdTokenWithoutEmailFallbacks(): void
    {
        // Same id_token structure but with preferred_username also removed.
        // This triggers the authentication failure when no email-capable claim is present.
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'aud'  => 'aabbccdd-1234-5678-abcd-ef0123456789',
                'iss'  => 'https://login.microsoftonline.com/11223344-aabb-ccdd-eeff-556677889900/v2.0',
                'iat'  => 1700000000,
                'nbf'  => 1700000000,
                'exp'  => 1700003600,
                'name' => 'External User (EXT)',
                'oid'  => 'oid-ext-guest-1234-5678-abcdef012345',
                'sid'  => 'session-id-1234-5678-abcdef012345',
                'sub'  => 'sub-id-ext-guest-abcdefghijklmnopqrstuvwxyz',
                'tid'  => '11223344-aabb-ccdd-eeff-556677889900',
                'ver'  => '2.0',
                // No email, upn, unique_name, or preferred_username
            ]);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage(self::MISSING_EMAIL_MESSAGE);

        $this->azureUserData->fill($resourceOwner);
    }
}
