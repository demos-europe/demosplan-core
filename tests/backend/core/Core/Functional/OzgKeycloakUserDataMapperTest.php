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

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Tests\Base\FunctionalTestCase;

class OzgKeycloakUserDataMapperTest extends FunctionalTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(OzgKeycloakUserDataMapper::class);
    }

    public function testMapUserDataWithIsPrivatePersonAttributeCreatesCitizenUser(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => 'privatperson@example.com',
                'given_name'         => 'Max',
                'family_name'        => 'Mustermann',
                'organisationId'     => 'PrivatpersonId',
                'organisationName'   => 'Privatperson',
                'sub'                => 'test-private-person-001',
                'preferred_username' => 'max.mustermann',
                'isPrivatePerson'    => true,
                'groups'             => [],
            ]);

        $parameterBag = new ParameterBag(['keycloak_group_role_string' => 'Beteiligung-Berechtigung']);
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData->fill($resourceOwner);

        $user = $this->sut->mapUserData($ozgKeycloakUserData);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals(User::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
        self::assertEquals('max.mustermann', $user->getLogin());
        self::assertEquals('Max', $user->getFirstname());
        self::assertEquals('Mustermann', $user->getLastname());

        // Check that user has CITIZEN role
        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());
        self::assertContains(Role::CITIZEN, $userRoles);
    }

    public function testMapUserDataWithIsPrivatePersonStringTrueCreatesCitizenUser(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => 'erika@example.com',
                'given_name'         => 'Erika',
                'family_name'        => 'Musterfrau',
                'organisationId'     => 'PrivatpersonId',
                'organisationName'   => 'Privatperson',
                'sub'                => 'test-private-person-002',
                'preferred_username' => 'erika.musterfrau',
                'isPrivatePerson'    => 'true',
                'groups'             => [],
            ]);

        $parameterBag = new ParameterBag(['keycloak_group_role_string' => 'Beteiligung-Berechtigung']);
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData->fill($resourceOwner);

        $user = $this->sut->mapUserData($ozgKeycloakUserData);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals(User::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());

        // Check that user has CITIZEN role
        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());
        self::assertContains(Role::CITIZEN, $userRoles);
    }

    public function testMapUserDataWithoutIsPrivatePersonAndOrgaDataCreatesOrgaUser(): void
    {
        $customerSubdomain = $this->getContainer()->get('demosplan\DemosPlanCoreBundle\Logic\User\CustomerService')->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'                           => 'orga.user@example.com',
                'given_name'                      => 'Hans',
                'family_name'                     => 'Schmidt',
                'organisationId'                  => 'orga-test-123',
                'organisationName'                => 'Test Organisation GmbH',
                'sub'                             => 'test-orga-user-001',
                'preferred_username'              => 'hans.schmidt',
                'UnternehmensanschriftStrasse'    => 'Hauptstraße',
                'UnternehmensanschriftHausnummer' => '42',
                'UnternehmensanschriftPLZ'        => '10115',
                'UnternehmensanschriftOrt'        => 'Berlin',
                'isPrivatePerson'                 => false,
                'groups'                          => [
                    '/Beteiligung-Organisation/Test Organisation GmbH',
                    "/Beteiligung-Berechtigung/{$customerSubdomain}/Institutions Sachbearbeitung",
                ],
            ]);

        $parameterBag = new ParameterBag(['keycloak_group_role_string' => 'Beteiligung-Berechtigung']);
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData->fill($resourceOwner);

        $user = $this->sut->mapUserData($ozgKeycloakUserData);

        self::assertInstanceOf(User::class, $user);
        self::assertNotEquals(User::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
        self::assertEquals('Test Organisation GmbH', $user->getOrga()->getName());
    }

    public function testBackwardCompatibilityWithCitizenRoleStillWorks(): void
    {
        $customerSubdomain = $this->getContainer()->get('demosplan\DemosPlanCoreBundle\Logic\User\CustomerService')->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => 'citizen.legacy@example.com',
                'given_name'         => 'Legacy',
                'family_name'        => 'Citizen',
                'organisationId'     => 'PrivatpersonId',
                'organisationName'   => 'Privatperson',
                'sub'                => 'test-legacy-citizen-001',
                'preferred_username' => 'legacy.citizen',
                // No isPrivatePerson attribute - should fallback to role-based detection
                'groups'           => [
                    '/Beteiligung-Organisation/Privatperson',
                    "/Beteiligung-Berechtigung/{$customerSubdomain}/Privatperson-Angemeldet",
                ],
            ]);

        $parameterBag = new ParameterBag(['keycloak_group_role_string' => 'Beteiligung-Berechtigung']);
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData->fill($resourceOwner);

        $user = $this->sut->mapUserData($ozgKeycloakUserData);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals(User::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());

        // Check that user has CITIZEN role
        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());
        self::assertContains(Role::CITIZEN, $userRoles);
    }

    public function testUpdateExistingCitizenUserWithIsPrivatePersonAttribute(): void
    {
        // First create a citizen user
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'              => 'existing.citizen@example.com',
                'given_name'         => 'Existing',
                'family_name'        => 'Citizen',
                'organisationId'     => 'PrivatpersonId',
                'organisationName'   => 'Privatperson',
                'sub'                => 'test-existing-citizen-001',
                'preferred_username' => 'existing.citizen',
                'isPrivatePerson'    => true,
                'groups'             => [],
            ]);

        $parameterBag = new ParameterBag(['keycloak_group_role_string' => 'Beteiligung-Berechtigung']);
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData->fill($resourceOwner);

        $firstUser = $this->sut->mapUserData($ozgKeycloakUserData);
        $firstUserId = $firstUser->getId();

        // Now login again with updated data
        $resourceOwner2 = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner2->method('toArray')
            ->willReturn([
                'email'              => 'updated.email@example.com',
                'given_name'         => 'Updated',
                'family_name'        => 'Name',
                'organisationId'     => 'PrivatpersonId',
                'organisationName'   => 'Privatperson',
                'sub'                => 'test-existing-citizen-001', // Same sub = same user
                'preferred_username' => 'updated.citizen',
                'isPrivatePerson'    => true,
                'groups'             => [],
            ]);

        $ozgKeycloakUserData2 = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData2->fill($resourceOwner2);

        $secondUser = $this->sut->mapUserData($ozgKeycloakUserData2);

        // Should be the same user (by ID)
        self::assertEquals($firstUserId, $secondUser->getId());
        // But with updated data
        self::assertEquals('Updated', $secondUser->getFirstname());
        self::assertEquals('Name', $secondUser->getLastname());
        self::assertEquals('updated.email@example.com', $secondUser->getEmail());
        // Still in citizen organization
        self::assertEquals(User::ANONYMOUS_USER_ORGA_ID, $secondUser->getOrga()->getId());
    }
}
