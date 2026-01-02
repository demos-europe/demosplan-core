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
}
