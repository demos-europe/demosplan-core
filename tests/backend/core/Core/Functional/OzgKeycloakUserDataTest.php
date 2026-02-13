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
                'groups'             => [],
            ]);

        $this->sut->fill($resourceOwner);

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
                'groups'             => [],
            ]);

        $this->sut->fill($resourceOwner);

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
                'groups'             => [],
            ]);

        $this->sut->fill($resourceOwner);

        $this->sut->checkMandatoryValuesExist();

        $stringRepresentation = (string) $this->sut;
        self::assertStringContainsString('isPrivatePerson: true', $stringRepresentation);
    }

    public function testUserInformationIsCorrectlyFilledFromResourceOwnerWithIsPrivatePerson(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'                           => self::TEST_EMAIL,
                'given_name'                      => 'John',
                'family_name'                     => 'Doe',
                'organisationId'                  => 'PrivatpersonId',
                'organisationName'                => 'Privatperson',
                'sub'                             => '456',
                'preferred_username'              => 'johndoe',
                'UnternehmensanschriftStrasse'    => 'Test Street',
                'UnternehmensanschriftHausnummer' => '10',
                'UnternehmensanschriftPLZ'        => '12345',
                'UnternehmensanschriftOrt'        => 'Test City',
                'isPrivatePerson'                 => true,
                'groups'                          => [],
            ]);

        $this->sut->fill($resourceOwner);

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

    public function testCartesianProductOfAffiliationsAndResponsibilities(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Multi',
                'family_name'        => 'Org',
                'sub'                => 'multi-user-123',
                'preferred_username' => 'multiorg',
                'organisation'       => [
                    ['id' => 'amt-a', 'name' => 'Amt A'],
                    ['id' => 'amt-b', 'name' => 'Amt B'],
                ],
                'responsibilities'   => [
                    ['id' => 'water', 'name' => 'Wasserwirtschaft'],
                    ['id' => 'litter', 'name' => 'Abfallwirtschaft'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/Org Multi',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $affiliations = $this->sut->getAffiliations();
        $responsibilities = $this->sut->getResponsibilities();

        self::assertCount(2, $affiliations);
        self::assertCount(2, $responsibilities);
        self::assertTrue($this->sut->hasAffiliations());
        self::assertTrue($this->sut->hasMultipleOrganisations());

        self::assertSame('amt-a', $affiliations[0]['id']);
        self::assertSame('Amt A', $affiliations[0]['name']);
        self::assertSame('amt-b', $affiliations[1]['id']);
        self::assertSame('Amt B', $affiliations[1]['name']);

        self::assertSame('water', $responsibilities[0]['id']);
        self::assertSame('Wasserwirtschaft', $responsibilities[0]['name']);
        self::assertSame('litter', $responsibilities[1]['id']);
        self::assertSame('Abfallwirtschaft', $responsibilities[1]['name']);
    }

    public function testAffiliationsOnlyWithoutResponsibilities(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Aff',
                'family_name'        => 'Only',
                'sub'                => 'aff-only-user',
                'preferred_username' => 'affonly',
                'organisation'       => [
                    ['id' => 'org-1', 'name' => 'Organisation One'],
                    ['id' => 'org-2', 'name' => 'Organisation Two'],
                ],
                'responsibilities'   => [],
                'groups'             => [
                    '/Beteiligung-Organisation/Aff Only',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        self::assertCount(2, $this->sut->getAffiliations());
        self::assertCount(0, $this->sut->getResponsibilities());
        self::assertTrue($this->sut->hasMultipleOrganisations());
    }

    public function testSingleAffiliationIsNotMultipleOrganisations(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Single',
                'family_name'        => 'Aff',
                'sub'                => 'single-aff-user',
                'preferred_username' => 'singleaff',
                'organisation'       => [
                    ['id' => 'only-org', 'name' => 'Only Organisation'],
                ],
                'responsibilities'   => [],
                'groups'             => [
                    '/Beteiligung-Organisation/Single Aff',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        self::assertFalse($this->sut->hasMultipleOrganisations());
        self::assertCount(1, $this->sut->getAffiliations());
    }

    public function testSingleAffiliationTimesSingleResponsibilityIsNotMultiple(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'One',
                'family_name'        => 'ByOne',
                'sub'                => '1x1-user',
                'preferred_username' => 'onebyone',
                'organisation'       => [
                    ['id' => 'aff-1', 'name' => 'Aff 1'],
                ],
                'responsibilities'   => [
                    ['id' => 'resp-1', 'name' => 'Resp 1'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/1x1',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        // 1 × 1 = 1, so not multiple
        self::assertFalse($this->sut->hasMultipleOrganisations());
    }

    public function testLegacyOrganisationIdFallbackToAffiliations(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Legacy',
                'family_name'        => 'User',
                'sub'                => 'legacy-user-789',
                'preferred_username' => 'legacyuser',
                'organisationId'     => 'legacy-org-id',
                'organisationName'   => 'Legacy Organisation',
                'groups'             => [
                    '/Beteiligung-Organisation/Legacy Organisation',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        // Should fall back to legacy organisationId as a single affiliation
        $affiliations = $this->sut->getAffiliations();
        self::assertCount(1, $affiliations);
        self::assertFalse($this->sut->hasMultipleOrganisations());
        self::assertSame('legacy-org-id', $affiliations[0]['id']);
        self::assertSame('Legacy Organisation', $affiliations[0]['name']);

        // Responsibilities should be empty (no responsibilities array in token)
        self::assertCount(0, $this->sut->getResponsibilities());
    }

    public function testEmptyArraysFallBackToOrganisationId(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Empty',
                'family_name'        => 'Arrays',
                'sub'                => 'empty-arrays-user',
                'preferred_username' => 'emptyarrays',
                'organisation'       => [],
                'responsibilities'   => [],
                'organisationId'     => 'fallback-org-id',
                'organisationName'   => 'Fallback Organisation',
                'groups'             => [
                    '/Beteiligung-Organisation/Fallback Organisation',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $affiliations = $this->sut->getAffiliations();
        self::assertCount(1, $affiliations);
        self::assertSame('fallback-org-id', $affiliations[0]['id']);
        self::assertSame('Fallback Organisation', $affiliations[0]['name']);
    }

    public function testMultiOrganisationValidationPassesWithRoles(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'Valid',
                'family_name'        => 'Multi',
                'sub'                => 'valid-multi-user',
                'preferred_username' => 'validmulti',
                'organisation'       => [
                    ['id' => 'org-1', 'name' => 'Org 1'],
                    ['id' => 'org-2', 'name' => 'Org 2'],
                ],
                'responsibilities'   => [
                    ['id' => 'resp-1', 'name' => 'Resp 1'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/Valid Multi',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        // Should not throw exception
        $this->sut->checkMandatoryValuesExist();

        self::assertTrue($this->sut->hasMultipleOrganisations());
    }

    public function testAffiliationWithoutNameUsesIdAsName(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'NoName',
                'family_name'        => 'Aff',
                'sub'                => 'noname-user',
                'preferred_username' => 'noname',
                'organisation'       => [
                    ['id' => 'org-without-name'],
                ],
                'responsibilities'   => [],
                'groups'             => [
                    '/Beteiligung-Organisation/NoName Aff',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $affiliations = $this->sut->getAffiliations();
        self::assertCount(1, $affiliations);
        // name should default to id value
        self::assertSame('org-without-name', $affiliations[0]['name']);
    }

    public function testResponsibilityWithoutNameUsesIdAsName(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'NoName',
                'family_name'        => 'Resp',
                'sub'                => 'noname-resp-user',
                'preferred_username' => 'nonameResp',
                'organisation'       => [
                    ['id' => 'some-org', 'name' => 'Some Org'],
                ],
                'responsibilities'   => [
                    ['id' => 'resp-without-name'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/NoName Resp',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $responsibilities = $this->sut->getResponsibilities();
        self::assertCount(1, $responsibilities);
        self::assertSame('resp-without-name', $responsibilities[0]['name']);
    }

    public function testToStringIncludesAffiliationsAndResponsibilities(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => self::TEST_EMAIL,
                'given_name'         => 'ToString',
                'family_name'        => 'Test',
                'sub'                => 'tostring-user',
                'preferred_username' => 'tostringtest',
                'organisation'       => [
                    ['id' => 'aff-1', 'name' => 'Aff Org 1'],
                    ['id' => 'aff-2', 'name' => 'Aff Org 2'],
                ],
                'responsibilities'   => [
                    ['id' => 'resp-1', 'name' => 'Resp 1'],
                    ['id' => 'resp-2', 'name' => 'Resp 2'],
                ],
                'groups' => [
                    '/Beteiligung-Organisation/ToString Test',
                    '/Beteiligung-Berechtigung/testcustomer/Fachplanung Administration',
                ],
            ]);

        $this->sut->fill($resourceOwner);

        $stringRepresentation = (string) $this->sut;
        self::assertStringContainsString('affiliations: [aff-1, aff-2]', $stringRepresentation);
        self::assertStringContainsString('responsibilities: [resp-1, resp-2]', $stringRepresentation);
    }
}
