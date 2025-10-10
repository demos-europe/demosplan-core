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

    /**
     * Creates a mock ResourceOwner for a private person.
     */
    private function createPrivatePersonResourceOwner(array $overrides = []): ResourceOwnerInterface
    {
        $defaults = [
            'email'              => 'privatperson@example.com',
            'given_name'         => 'Max',
            'family_name'        => 'Mustermann',
            'organisationId'     => 'PrivatpersonId',
            'organisationName'   => 'Privatperson',
            'sub'                => 'test-private-person-001',
            'preferred_username' => 'max.mustermann',
            'isPrivatePerson'    => true,
            'groups'             => [],
        ];

        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')->willReturn(array_merge($defaults, $overrides));

        return $resourceOwner;
    }

    /**
     * Creates a mock ResourceOwner for an organization user.
     */
    private function createOrganizationResourceOwner(array $overrides = []): ResourceOwnerInterface
    {
        $customerSubdomain = $this->getContainer()->get('demosplan\DemosPlanCoreBundle\Logic\User\CustomerService')->getCurrentCustomer()->getSubdomain();

        $defaults = [
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
        ];

        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')->willReturn(array_merge($defaults, $overrides));

        return $resourceOwner;
    }

    /**
     * Helper to create OzgKeycloakUserData and map it to a User entity.
     */
    private function mapResourceOwnerToUser(ResourceOwnerInterface $resourceOwner): User
    {
        $parameterBag = new ParameterBag(['keycloak_group_role_string' => 'Beteiligung-Berechtigung']);
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData->fill($resourceOwner);

        return $this->sut->mapUserData($ozgKeycloakUserData);
    }

    /**
     * Asserts that a user has the CITIZEN role.
     */
    private function assertUserHasCitizenRole(User $user): void
    {
        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());
        self::assertContains(Role::CITIZEN, $userRoles);
    }

    public function testMapUserDataWithIsPrivatePersonAttributeCreatesCitizenUser(): void
    {
        $resourceOwner = $this->createPrivatePersonResourceOwner();
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals(User::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
        self::assertEquals('max.mustermann', $user->getLogin());
        self::assertEquals('Max', $user->getFirstname());
        self::assertEquals('Mustermann', $user->getLastname());
        $this->assertUserHasCitizenRole($user);
    }

    public function testMapUserDataWithIsPrivatePersonStringTrueCreatesCitizenUser(): void
    {
        $resourceOwner = $this->createPrivatePersonResourceOwner([
            'email'              => 'erika@example.com',
            'given_name'         => 'Erika',
            'family_name'        => 'Musterfrau',
            'sub'                => 'test-private-person-002',
            'preferred_username' => 'erika.musterfrau',
            'isPrivatePerson'    => 'true',
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals(User::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
        $this->assertUserHasCitizenRole($user);
    }

    public function testMapUserDataWithoutIsPrivatePersonAndOrgaDataCreatesOrgaUser(): void
    {
        $resourceOwner = $this->createOrganizationResourceOwner();
        $user = $this->mapResourceOwnerToUser($resourceOwner);

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

        $user = $this->mapResourceOwnerToUser($resourceOwner);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals(User::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
        $this->assertUserHasCitizenRole($user);
    }

    public function testUpdateExistingCitizenUserWithIsPrivatePersonAttribute(): void
    {
        // First create a citizen user
        $resourceOwner = $this->createPrivatePersonResourceOwner([
            'email'              => 'existing.citizen@example.com',
            'given_name'         => 'Existing',
            'family_name'        => 'Citizen',
            'sub'                => 'test-existing-citizen-001',
            'preferred_username' => 'existing.citizen',
        ]);
        $firstUser = $this->mapResourceOwnerToUser($resourceOwner);
        $firstUserId = $firstUser->getId();

        // Now login again with updated data
        $resourceOwner2 = $this->createPrivatePersonResourceOwner([
            'email'              => 'updated.email@example.com',
            'given_name'         => 'Updated',
            'family_name'        => 'Name',
            'sub'                => 'test-existing-citizen-001', // Same sub = same user
            'preferred_username' => 'updated.citizen',
        ]);
        $secondUser = $this->mapResourceOwnerToUser($resourceOwner2);

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
