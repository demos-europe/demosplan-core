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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
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
     * Creates a mock ResourceOwner with resource_access roles (NEW method).
     */
    private function createResourceAccessResourceOwner(array $overrides = []): ResourceOwnerInterface
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $defaults = [
            'email'                           => 'resource.user@example.com',
            'given_name'                      => 'Resource',
            'family_name'                     => 'Access User',
            'organisationId'                  => 'orga-resource-123',
            'organisationName'                => 'Resource Access Organisation',
            'sub'                             => 'test-resource-user-001',
            'preferred_username'              => 'resource.user',
            'UnternehmensanschriftStrasse'    => 'Teststrasse',
            'UnternehmensanschriftHausnummer' => '123',
            'UnternehmensanschriftPLZ'        => '10115',
            'UnternehmensanschriftOrt'        => 'Berlin',
            'isPrivatePerson'                 => false,
            'resource_access'                 => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-SB'],
                ],
            ],
            'groups' => [], // No groups - should use resource_access
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
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

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
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $parameterBag = new ParameterBag([
            'keycloak_group_role_string' => 'Beteiligung-Berechtigung',
            'keycloak_client_id'         => "diplan-develop-beteiligung-{$customerSubdomain}",
        ]);
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag);
        $ozgKeycloakUserData->fill($resourceOwner, $customerSubdomain);

        return $this->sut->mapUserData($ozgKeycloakUserData);
    }

    /**
     * Asserts that a user has the CITIZEN role.
     */
    private function assertUserHasCitizenRole(User $user): void
    {
        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());
        self::assertContains(RoleInterface::CITIZEN, $userRoles);
    }

    public function testMapUserDataWithIsPrivatePersonAttributeCreatesCitizenUser(): void
    {
        $resourceOwner = $this->createPrivatePersonResourceOwner();
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        self::assertEquals(UserInterface::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
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

        self::assertEquals(UserInterface::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
        $this->assertUserHasCitizenRole($user);
    }

    public function testMapUserDataWithoutIsPrivatePersonAndOrgaDataCreatesOrgaUser(): void
    {
        $resourceOwner = $this->createOrganizationResourceOwner();
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        self::assertNotEquals(UserInterface::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
        self::assertEquals('Test Organisation GmbH', $user->getOrga()->getName());
    }

    public function testBackwardCompatibilityWithCitizenRoleStillWorks(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

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

        self::assertEquals(UserInterface::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());
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
        self::assertEquals(UserInterface::ANONYMOUS_USER_ORGA_ID, $secondUser->getOrga()->getId());
    }

    /**
     * Test NEW resource_access based role extraction (ADO-43125).
     */
    public function testMapUserDataWithResourceAccessRolesFPSB(): void
    {
        $resourceOwner = $this->createResourceAccessResourceOwner();
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        self::assertEquals('resource.user', $user->getLogin());
        self::assertEquals('Resource', $user->getFirstname());
        self::assertEquals('Access User', $user->getLastname());
        self::assertNotEquals(UserInterface::ANONYMOUS_USER_ORGA_ID, $user->getOrga()->getId());

        // Check that user has the correct role (FP-SB -> Fachplanung Sachbearbeitung)
        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());
        self::assertContains(RoleInterface::PLANNING_AGENCY_WORKER, $userRoles);
    }

    /**
     * Test resource_access with multiple technical roles.
     */
    public function testMapUserDataWithMultipleResourceAccessRoles(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'multi.role@example.com',
            'sub'                => 'test-multi-role-001',
            'preferred_username' => 'multi.role',
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A', 'I-K'], // Multiple roles
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());

        // Should have both roles mapped
        self::assertContains(RoleInterface::PLANNING_AGENCY_ADMIN, $userRoles); // FP-A
        self::assertContains(RoleInterface::PUBLIC_AGENCY_COORDINATION, $userRoles); // I-K
    }

    /**
     * Test that resource_access takes precedence over groups (backward compatibility).
     */
    public function testResourceAccessTakesPrecedenceOverGroups(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'precedence.test@example.com',
            'sub'                => 'test-precedence-001',
            'preferred_username' => 'precedence.test',
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'], // Admin role from resource_access
                ],
            ],
            'groups' => [
                '/Beteiligung-Organisation/Test Org',
                "/Beteiligung-Berechtigung/{$customerSubdomain}/Institutions Sachbearbeitung", // Worker role from groups
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());

        // Should have ADMIN role from resource_access, NOT worker role from groups
        self::assertContains(RoleInterface::PLANNING_AGENCY_ADMIN, $userRoles);
        self::assertNotContains(RoleInterface::PUBLIC_AGENCY_WORKER, $userRoles);
    }

    /**
     * Test fallback to group-based roles when resource_access is empty.
     */
    public function testFallbackToGroupBasedRolesWhenResourceAccessEmpty(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'fallback.test@example.com',
            'sub'                => 'test-fallback-001',
            'preferred_username' => 'fallback.test',
            'resource_access'    => [], // Empty resource_access
            'groups'             => [
                '/Beteiligung-Organisation/Test Org',
                "/Beteiligung-Berechtigung/{$customerSubdomain}/Institutions Sachbearbeitung",
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());

        // Should have worker role from groups (fallback)
        self::assertContains(RoleInterface::PUBLIC_AGENCY_WORKER, $userRoles);
    }

    /**
     * Test that unknown technical roles in resource_access are logged but don't break authentication.
     */
    public function testUnknownTechnicalRolesAreHandledGracefully(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'unknown.role@example.com',
            'sub'                => 'test-unknown-role-001',
            'preferred_username' => 'unknown.role',
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-SB', 'UNKNOWN-ROLE', 'I-K'], // Mix of known and unknown
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        $userRoles = array_map(static fn ($role) => $role->getCode(), $user->getDplanroles()->toArray());

        // Should have the known roles
        self::assertContains(RoleInterface::PLANNING_AGENCY_WORKER, $userRoles); // FP-SB
        self::assertContains(RoleInterface::PUBLIC_AGENCY_COORDINATION, $userRoles); // I-K
        // UNKNOWN-ROLE should be ignored (not cause failure)
    }
}
