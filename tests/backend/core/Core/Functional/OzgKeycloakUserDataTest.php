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

use demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper\RoleMapper;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Tests\Base\FunctionalTestCase;

class OzgKeycloakUserDataTest extends FunctionalTestCase
{
    public const TEST_EMAIL = 'test@example.com';
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $parameterBag = new ParameterBag([
            'keycloak_group_role_string'  => 'Beteiligung-Berechtigung',
            'oauth_keycloak_client_id'    => 'diplan-develop-beteiligung-test',
        ]);
        $roleMapper = new RoleMapper(new NullLogger());
        $this->sut = new OzgKeycloakUserData(new NullLogger(), $parameterBag, $roleMapper);
    }

    public function testIsPrivatePersonReturnsTrueWhenAttributeIsBooleanTrue(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'John',
                'family_name'        => 'Doe',
                'sub'                => '456',
                'preferred_username' => 'johndoe',
                'isPrivatePerson'    => true,
                'groups'             => [], // No groups or org data needed for private persons
            ]);

        $this->sut->fill($resourceOwner);

        // Verify validation passes without organization attributes
        $this->sut->checkMandatoryValuesExist();

        self::assertTrue($this->sut->isPrivatePerson());
    }

    public function testIsPrivatePersonReturnsTrueWhenAttributeIsStringTrue(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Jane',
                'family_name'        => 'Smith',
                'sub'                => '789',
                'preferred_username' => 'janesmith',
                'isPrivatePerson'    => 'true',
                'groups'             => [], // No groups or org data needed for private persons
            ]);

        $this->sut->fill($resourceOwner);

        // Verify validation passes without organization attributes
        $this->sut->checkMandatoryValuesExist();

        self::assertTrue($this->sut->isPrivatePerson());
    }

    public function testIsPrivatePersonReturnsFalseWhenAttributeIsMissing(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Alice',
                'family_name'        => 'Brown',
                'organisationId'     => '123',
                'organisationName'   => 'Test Organisation',
                'sub'                => '101',
                'preferred_username' => 'alicebrown',
                'groups'             => [
                    '/Beteiligung-Organisation/Test Organisation',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        self::assertFalse($this->sut->isPrivatePerson());
    }

    public function testIsPrivatePersonReturnsFalseWhenAttributeIsFalse(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Bob',
                'family_name'        => 'Johnson',
                'organisationId'     => '456',
                'organisationName'   => 'Company Inc',
                'sub'                => '202',
                'preferred_username' => 'bobjohnson',
                'isPrivatePerson'    => false,
                'groups'             => [
                    '/Beteiligung-Organisation/Company Inc',
                    '/Beteiligung-Berechtigung/testcustomer/Institutions Sachbearbeitung',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        self::assertFalse($this->sut->isPrivatePerson());
    }

    public function testIsPrivatePersonReturnsFalseWhenAttributeIsStringFalse(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Charlie',
                'family_name'        => 'Davis',
                'organisationId'     => '789',
                'organisationName'   => 'Enterprise LLC',
                'sub'                => '303',
                'preferred_username' => 'charliedavis',
                'isPrivatePerson'    => 'false',
                'groups'             => [
                    '/Beteiligung-Organisation/Enterprise LLC',
                    '/Beteiligung-Berechtigung/testcustomer/Support',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        self::assertFalse($this->sut->isPrivatePerson());
    }

    public function testToStringIncludesIsPrivatePersonValue(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Test',
                'family_name'        => 'User',
                'sub'                => '999',
                'preferred_username' => 'testuser',
                'isPrivatePerson'    => true,
                'groups'             => [], // No groups or org data needed for private persons
            ]);

        $this->sut->fill($resourceOwner);

        // Verify validation passes without organization attributes
        $this->sut->checkMandatoryValuesExist();

        $stringRepresentation = (string) $this->sut;
        self::assertStringContainsString('isPrivatePerson: true', $stringRepresentation);
    }

    public function testUserInformationIsCorrectlyFilledFromResourceOwnerWithIsPrivatePerson(): void
    {
        // Test backward compatibility: private persons can still include organization data
        // even though it's not required. This ensures existing tokens continue to work.
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'                           => self::TEST_EMAIL,
                'given_name'                      => 'John',
                'family_name'                     => 'Doe',
                'organisationId'                  => 'PrivatpersonId', // Optional for backward compatibility
                'organisationName'                => 'Privatperson', // Optional for backward compatibility
                'sub'                             => '456',
                'preferred_username'              => 'johndoe',
                'UnternehmensanschriftStrasse'    => 'Test Street',
                'UnternehmensanschriftHausnummer' => '10',
                'UnternehmensanschriftPLZ'        => '12345',
                'UnternehmensanschriftOrt'        => 'Test City',
                'isPrivatePerson'                 => true,
                'groups'                          => [], // No groups needed for private persons
            ]);

        $this->sut->fill($resourceOwner);

        // Verify validation passes with organization attributes (backward compatibility)
        $this->sut->checkMandatoryValuesExist();

        self::assertEquals(self::TEST_EMAIL, $this->sut->getEmailAddress());
        self::assertEquals('John', $this->sut->getFirstName());
        self::assertEquals('Doe', $this->sut->getLastName());
        self::assertEquals('PrivatpersonId', $this->sut->getOrganisationId());
        self::assertEquals('Privatperson', $this->sut->getOrganisationName());
        self::assertEquals('456', $this->sut->getUserId());
        self::assertEquals('johndoe', $this->sut->getUserName());
        self::assertTrue($this->sut->isPrivatePerson());
    }

    public function testMultipleResponsibilitiesAreParsedCorrectly(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Multi',
                'family_name'        => 'Org',
                'sub'                => 'multi-user-123',
                'preferred_username' => 'multiorg',
                'responsibilities'   => [
                    ['responsibility' => 'org-gw-id-1', 'orgaName' => 'Organisation One'],
                    ['responsibility' => 'org-gw-id-2', 'orgaName' => 'Organisation Two'],
                    ['responsibility' => 'org-gw-id-3', 'orgaName' => 'Organisation Three'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/Org Multi',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $responsibilities = $this->sut->getResponsibilities();

        self::assertCount(3, $responsibilities);
        self::assertTrue($this->sut->hasMultipleResponsibilities());

        self::assertSame('org-gw-id-1', $responsibilities[0]['responsibility']);
        self::assertSame('Organisation One', $responsibilities[0]['orgaName']);
        self::assertSame('org-gw-id-2', $responsibilities[1]['responsibility']);
        self::assertSame('Organisation Two', $responsibilities[1]['orgaName']);
        self::assertSame('org-gw-id-3', $responsibilities[2]['responsibility']);
        self::assertSame('Organisation Three', $responsibilities[2]['orgaName']);
    }

    public function testSingleResponsibilityIsNotMultiple(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Single',
                'family_name'        => 'Org',
                'sub'                => 'single-user-456',
                'preferred_username' => 'singleorg',
                'responsibilities'   => [
                    ['responsibility' => 'org-gw-id-only', 'orgaName' => 'Only Organisation'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/Single Org',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        self::assertFalse($this->sut->hasMultipleResponsibilities());
        self::assertCount(1, $this->sut->getResponsibilities());

        $primary = $this->sut->getPrimaryResponsibility();
        self::assertNotNull($primary);
        self::assertSame('org-gw-id-only', $primary['responsibility']);
    }

    public function testLegacyOrganisationIdFallbackToResponsibilities(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'            => self::TEST_EMAIL,
                'given_name'       => 'Legacy',
                'family_name'      => 'User',
                'sub'              => 'legacy-user-789',
                'preferred_username' => 'legacyuser',
                'organisationId'   => 'legacy-org-id',
                'organisationName' => 'Legacy Organisation',
                'groups'           => [
                    '/Beteiligung-Organisation/Legacy Organisation',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        // Should fall back to legacy organisationId as a single responsibility
        $responsibilities = $this->sut->getResponsibilities();
        self::assertCount(1, $responsibilities);
        self::assertFalse($this->sut->hasMultipleResponsibilities());
        self::assertSame('legacy-org-id', $responsibilities[0]['responsibility']);
        self::assertSame('Legacy Organisation', $responsibilities[0]['orgaName']);
    }

    public function testEmptyResponsibilitiesArrayFallsBackToLegacy(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Empty',
                'family_name'        => 'Resp',
                'sub'                => 'empty-resp-user',
                'preferred_username' => 'emptyresp',
                'responsibilities'   => [], // Empty array
                'organisationId'     => 'fallback-org-id',
                'organisationName'   => 'Fallback Organisation',
                'groups'             => [
                    '/Beteiligung-Organisation/Fallback Organisation',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $responsibilities = $this->sut->getResponsibilities();
        self::assertCount(1, $responsibilities);
        self::assertSame('fallback-org-id', $responsibilities[0]['responsibility']);
    }

    public function testMultiResponsibilityValidationPassesWithRoles(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Valid',
                'family_name'        => 'Multi',
                'sub'                => 'valid-multi-user',
                'preferred_username' => 'validmulti',
                'responsibilities'   => [
                    ['responsibility' => 'org-1', 'orgaName' => 'Org 1'],
                    ['responsibility' => 'org-2', 'orgaName' => 'Org 2'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/Valid Multi',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        // Should not throw exception
        $this->sut->checkMandatoryValuesExist();

        self::assertTrue($this->sut->hasMultipleResponsibilities());
    }

    public function testResponsibilityWithoutOrgaNameUsesResponsibilityAsName(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'NoName',
                'family_name'        => 'Resp',
                'sub'                => 'noname-user',
                'preferred_username' => 'noname',
                'responsibilities'   => [
                    ['responsibility' => 'org-without-name'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/NoName Resp',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $responsibilities = $this->sut->getResponsibilities();
        self::assertCount(1, $responsibilities);
        // orgaName should default to responsibility value
        self::assertSame('org-without-name', $responsibilities[0]['orgaName']);
    }

    public function testToStringIncludesResponsibilities(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'ToString',
                'family_name'        => 'Test',
                'sub'                => 'tostring-user',
                'preferred_username' => 'tostringtest',
                'responsibilities'   => [
                    ['responsibility' => 'resp-1', 'orgaName' => 'Resp Org 1'],
                    ['responsibility' => 'resp-2', 'orgaName' => 'Resp Org 2'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/ToString Test',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $stringRepresentation = (string) $this->sut;
        // The toString format shows responsibilities as a list, e.g. "responsibilities: [resp-1, resp-2]"
        self::assertStringContainsString('responsibilities: [resp-1, resp-2]', $stringRepresentation);
    }
}
