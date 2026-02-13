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
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakGroupBasedRoleMapper;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper\DepartmentMapper;
use demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper\RoleMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRoleInCustomerRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ValueObject\OzgKeycloakUserData;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Log\NullLogger;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\FunctionalTestCase;

class OzgKeycloakUserDataMapperTest extends FunctionalTestCase
{
    private const MSG_SAME_USER = 'Must be the same user';
    private const ORG_NAME_FROM_TOKEN = 'Original Name From Token';
    private const ORG_NAME_AMT_A = 'Original Amt A';
    private const ORG_NAME_AMT_B = 'Original Amt B';

    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $departmentMapper = new DepartmentMapper(
            $this->getContainer()->get(EntityManagerInterface::class),
            new NullLogger()
        );

        $groupBasedRoleMapper = new OzgKeycloakGroupBasedRoleMapper(
            $this->getContainer()->get(GlobalConfig::class),
            new NullLogger(),
            $this->getContainer()->get(RoleRepository::class)
        );

        $this->sut = new OzgKeycloakUserDataMapper(
            $this->getContainer()->get(CustomerService::class),
            $this->getContainer()->get(DepartmentRepository::class),
            $this->getContainer()->get(EntityManagerInterface::class),
            new NullLogger(),
            $this->getContainer()->get(OrgaRepository::class),
            $this->getContainer()->get(OrgaService::class),
            $this->getContainer()->get(OrgaTypeRepository::class),
            $this->getContainer()->get(RoleRepository::class),
            $this->getContainer()->get(UserRepository::class),
            $this->getContainer()->get(UserRoleInCustomerRepository::class),
            $this->getContainer()->get(UserService::class),
            $this->getContainer()->get(ValidatorInterface::class),
            $departmentMapper,
            $groupBasedRoleMapper
        );
    }

    public function testCreateUserWithNoDepartmentInToken(): void
    {
        $attributes = $this->getBaseOrgaAttributes();
        // No 'Organisationseinheit' field = empty department

        $userData = $this->createUserData($attributes);
        $user = $this->sut->mapUserData($userData);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals(Department::DEFAULT_DEPARTMENT_NAME,
            $user->getDepartment()->getName());
    }

    public function testCreateUserWithDepartmentInToken(): void
    {
        $attributes = $this->getBaseOrgaAttributes();
        $attributes['Organisationseinheit'] = 'IT Department';

        $userData = $this->createUserData($attributes);
        $user = $this->sut->mapUserData($userData);

        self::assertInstanceOf(User::class, $user);
        self::assertEquals('IT Department',
            $user->getDepartment()->getName());
    }

    public function testUpdateUserWithSameDepartment(): void
    {
        // First login - create user with department
        $attributes = $this->getBaseOrgaAttributes();
        $attributes['Organisationseinheit'] = 'Finance';

        $userData = $this->createUserData($attributes);
        $user = $this->sut->mapUserData($userData);
        $originalDepartment = $user->getDepartment();

        // Second login - same department
        $userData2 = $this->createUserData($attributes);
        $user2 =
            $this->sut->mapUserData($userData2);

        self::assertEquals($originalDepartment->getId(),
            $user2->getDepartment()->getId());
        self::assertEquals('Finance', $user2->getDepartment()->getName());
    }

    public function testUpdateUserWithDifferentDepartment(): void
    {
        // First login
        $attributes = $this->getBaseOrgaAttributes();
        $attributes['Organisationseinheit'] = 'HR';

        $userData = $this->createUserData($attributes);
        $user = $this->sut->mapUserData($userData);
        self::assertEquals('HR', $user->getDepartment()->getName());
        $firstDepartmentId = $user->getDepartment()->getId();

        // Second login with different department
        $attributes['Organisationseinheit'] = 'Legal';
        $userData2 = $this->createUserData($attributes);
        $user2 = $this->sut->mapUserData($userData2);

        self::assertEquals('Legal', $user2->getDepartment()->getName());
        self::assertNotEquals($firstDepartmentId, $user2->getDepartment()->getId());
    }

    private function createUserData(array $attributes): OzgKeycloakUserData
    {
        $resourceOwner = new KeycloakResourceOwner($attributes);
        $roleMapper = new RoleMapper(new NullLogger());
        $userData = new OzgKeycloakUserData(
            new NullLogger(),
            new ParameterBag([
                'keycloak_group_role_string'  => 'PlaceholderForKeycloakForRole',
                'oauth_keycloak_client_id'    => 'test-client-id',
            ]),
            $roleMapper
        );
        $userData->fill($resourceOwner);

        return $userData;
    }

    private function getBaseOrgaAttributes(): array
    {
        return [
            'email'              => 'test@example.com',
            'preferred_username' => 'minnimouse',  // This maps to userName
            'given_name'         => 'Minnie',      // Fixed: was givenName
            'family_name'        => 'Mouse',       // Fixed: was surname
            'organisationId'     => '123-test-org',
            'organisationName'   => 'Amt Nordwest',
            'sub'                => '456-user-id',
            'groups'             => [
                '/Beteiligung-Organisation/Amt Nordwest',
                '/PlaceholderForKeycloakForRole/hindsight/Fachplanung Administration',
            ], // This provides roles
        ];
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
            'keycloak_group_role_string'  => 'Beteiligung-Berechtigung',
            'oauth_keycloak_client_id'    => "diplan-develop-beteiligung-{$customerSubdomain}",
        ]);
        $roleMapper = new RoleMapper(new NullLogger());
        $ozgKeycloakUserData = new OzgKeycloakUserData(new NullLogger(), $parameterBag, $roleMapper);
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

    /**
     * Test cartesian product: 2 affiliations × 2 responsibilities = 4 organisations.
     */
    public function testCartesianProductCreates4Organisations(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'cartesian@example.com',
            'sub'                => 'test-cartesian-001',
            'preferred_username' => 'cartesian.user',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'AMT-A', 'name' => 'Amt A'],
                ['id' => 'AMT-B', 'name' => 'Amt B'],
            ],
            'responsibilities'   => [
                ['id' => 'WATER', 'name' => 'Wasserwirtschaft'],
                ['id' => 'LITTER', 'name' => 'Abfallwirtschaft'],
            ],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        $orgGwIds = [];
        foreach ($user->getOrganisations() as $orga) {
            $orgGwIds[] = $orga->getGwId();
        }
        sort($orgGwIds);

        self::assertCount(4, $orgGwIds);
        self::assertSame(['AMT-A.LITTER', 'AMT-A.WATER', 'AMT-B.LITTER', 'AMT-B.WATER'], $orgGwIds);
    }

    /**
     * Test affiliations-only token creates orgs from affiliations without cartesian product.
     */
    public function testAffiliationsOnlyCreatesOrgsFromAffiliations(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'aff.only@example.com',
            'sub'                => 'test-aff-only-001',
            'preferred_username' => 'aff.only',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'DEPT-X', 'name' => 'Department X'],
                ['id' => 'DEPT-Y', 'name' => 'Department Y'],
            ],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        $orgGwIds = [];
        foreach ($user->getOrganisations() as $orga) {
            $orgGwIds[] = $orga->getGwId();
        }
        sort($orgGwIds);

        self::assertCount(2, $orgGwIds);
        self::assertSame(['DEPT-X', 'DEPT-Y'], $orgGwIds);
    }

    /**
     * Test single affiliation creates one org and uses the single-entry flow.
     */
    public function testSingleAffiliationCreatesSingleOrg(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'single.aff@example.com',
            'sub'                => 'test-single-aff-001',
            'preferred_username' => 'single.aff',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'SINGLE-AFF', 'name' => 'Single Affiliation Org'],
            ],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        self::assertCount(1, $user->getOrganisations());
        self::assertSame('SINGLE-AFF', $user->getOrganisations()->first()->getGwId());
        self::assertSame('Single Affiliation Org', $user->getOrganisations()->first()->getName());
    }

    /**
     * Test stale orgs are removed when token changes from multi-org to fewer orgs.
     */
    public function testStaleOrgsRemovedWhenTokenChanges(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        // First login: 2 affiliations = 2 orgs
        $resourceOwner1 = $this->createResourceAccessResourceOwner([
            'email'              => 'sync.test@example.com',
            'sub'                => 'test-sync-001',
            'preferred_username' => 'sync.test',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'SYNC-ORG-A', 'name' => 'Sync Org A'],
                ['id' => 'SYNC-ORG-B', 'name' => 'Sync Org B'],
            ],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner1);
        self::assertCount(2, $user->getOrganisations());

        // Second login: only 1 affiliation — SYNC-ORG-B should be removed
        $resourceOwner2 = $this->createResourceAccessResourceOwner([
            'email'              => 'sync.test@example.com',
            'sub'                => 'test-sync-001',
            'preferred_username' => 'sync.test',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'SYNC-ORG-A', 'name' => 'Sync Org A'],
            ],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user2 = $this->mapResourceOwnerToUser($resourceOwner2);

        self::assertCount(1, $user2->getOrganisations());
        self::assertSame('SYNC-ORG-A', $user2->getOrganisations()->first()->getGwId());
    }

    /**
     * Test stale orgs are removed when multi-org user switches to cartesian product.
     */
    public function testStaleOrgsRemovedWhenSwitchingToCartesianProduct(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        // First login: 3 affiliations (old format)
        $resourceOwner1 = $this->createResourceAccessResourceOwner([
            'email'              => 'switch.cp@example.com',
            'sub'                => 'test-switch-cp-001',
            'preferred_username' => 'switch.cp',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'OLD-ORG-1', 'name' => 'Old Org 1'],
                ['id' => 'OLD-ORG-2', 'name' => 'Old Org 2'],
                ['id' => 'OLD-ORG-3', 'name' => 'Old Org 3'],
            ],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner1);
        self::assertCount(3, $user->getOrganisations());

        // Second login: 2 affiliations × 1 responsibility (cartesian product)
        // Old orgs should be completely replaced
        $resourceOwner2 = $this->createResourceAccessResourceOwner([
            'email'              => 'switch.cp@example.com',
            'sub'                => 'test-switch-cp-001',
            'preferred_username' => 'switch.cp',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'NEW-AMT-A', 'name' => 'New Amt A'],
                ['id' => 'NEW-AMT-B', 'name' => 'New Amt B'],
            ],
            'responsibilities'   => [
                ['id' => 'WATER', 'name' => 'Wasserwirtschaft'],
            ],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user2 = $this->mapResourceOwnerToUser($resourceOwner2);

        $orgGwIds = [];
        foreach ($user2->getOrganisations() as $orga) {
            $orgGwIds[] = $orga->getGwId();
        }
        sort($orgGwIds);

        self::assertCount(2, $orgGwIds);
        self::assertSame(['NEW-AMT-A.WATER', 'NEW-AMT-B.WATER'], $orgGwIds);
    }

    /**
     * Test that org name from token is used on create but NOT overwritten on subsequent logins.
     */
    public function testOrgNameNotOverwrittenOnUpdate(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        // First login: creates org with name from token
        $resourceOwner1 = $this->createResourceAccessResourceOwner([
            'email'              => 'orgname.test@example.com',
            'sub'                => 'test-orgname-001',
            'preferred_username' => 'orgname.test',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'ORGNAME-TEST', 'name' => self::ORG_NAME_FROM_TOKEN],
            ],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner1);
        $orga = $user->getOrganisations()->first();
        self::assertSame(self::ORG_NAME_FROM_TOKEN, $orga->getName());

        // Simulate FPA user renaming the org via the UI
        $orga->setName('User-Modified Name');
        $this->getEntityManager()->persist($orga);
        $this->getEntityManager()->flush();

        // Second login: token still has the old name
        $resourceOwner2 = $this->createResourceAccessResourceOwner([
            'email'              => 'orgname.test@example.com',
            'sub'                => 'test-orgname-001',
            'preferred_username' => 'orgname.test',
            'organisationId'     => '',
            'organisationName'   => '',
            'organisation'       => [
                ['id' => 'ORGNAME-TEST', 'name' => self::ORG_NAME_FROM_TOKEN],
            ],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user2 = $this->mapResourceOwnerToUser($resourceOwner2);
        $orga2 = $user2->getOrganisations()->first();

        // Name must still be the user-modified one, NOT overwritten by the token
        self::assertSame('User-Modified Name', $orga2->getName());
    }

    /**
     * Test organisationId fallback still works when no arrays are present.
     */
    public function testOrganisationIdFallbackWithEmptyArrays(): void
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $resourceOwner = $this->createResourceAccessResourceOwner([
            'email'              => 'fallback.orgid@example.com',
            'sub'                => 'test-fallback-orgid-001',
            'preferred_username' => 'fallback.orgid',
            'organisationId'     => 'LEGACY-ORG-ID',
            'organisationName'   => 'Legacy Organisation',
            'organisation'       => [],
            'responsibilities'   => [],
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ]);
        $user = $this->mapResourceOwnerToUser($resourceOwner);

        // Should use the organisationId fallback → single affiliation → single-entry flow
        self::assertCount(1, $user->getOrganisations());
        self::assertSame('LEGACY-ORG-ID', $user->getOrganisations()->first()->getGwId());
        self::assertSame('Legacy Organisation', $user->getOrganisations()->first()->getName());
    }

    // ========================================================================
    // Re-login scenarios: existing user logs in with modified token data
    // ========================================================================

    /**
     * Helper: create a resource owner for re-login tests with a stable user identity.
     *
     * @param array<string, mixed> $tokenOverrides Fields to set/override on the token
     */
    private function createReloginResourceOwner(array $tokenOverrides): ResourceOwnerInterface
    {
        $customerSubdomain = $this->getContainer()->get(CustomerService::class)->getCurrentCustomer()->getSubdomain();

        $defaults = [
            'email'              => 'relogin@example.com',
            'sub'                => 'test-relogin-stable-001',
            'preferred_username' => 'relogin.user',
            'given_name'         => 'Re',
            'family_name'        => 'Login',
            'organisationId'     => '',
            'organisationName'   => '',
            'isPrivatePerson'    => false,
            'resource_access'    => [
                "diplan-develop-beteiligung-{$customerSubdomain}" => [
                    'roles' => ['FP-A'],
                ],
            ],
        ];

        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')->willReturn(array_merge($defaults, $tokenOverrides));

        return $resourceOwner;
    }

    /**
     * Helper: extract sorted gwIds from a user's organisations.
     *
     * @return array<int, string>
     */
    private function getSortedGwIds(User $user): array
    {
        $gwIds = [];
        foreach ($user->getOrganisations() as $orga) {
            $gwIds[] = $orga->getGwId();
        }
        sort($gwIds);

        return $gwIds;
    }

    /**
     * Helper: extract sorted org names from a user's organisations.
     *
     * @return array<int, string>
     */
    private function getSortedOrgNames(User $user): array
    {
        $names = [];
        foreach ($user->getOrganisations() as $orga) {
            $names[] = $orga->getName();
        }
        sort($names);

        return $names;
    }

    /**
     * Helper: perform a login → relogin cycle and assert org sync.
     *
     * @param array<int, array{id: string, name: string}> $firstOrgs
     * @param array<int, array{id: string, name: string}> $firstResps
     * @param array<int, string>                          $expectedGwIds1
     * @param array<int, array{id: string, name: string}> $secondOrgs
     * @param array<int, array{id: string, name: string}> $secondResps
     * @param array<int, string>                          $expectedGwIds2
     * @param array<string, mixed>                        $extraOverrides
     */
    private function assertReloginSyncsOrgs(
        string $identity,
        array $firstOrgs,
        array $firstResps,
        array $expectedGwIds1,
        array $secondOrgs,
        array $secondResps,
        array $expectedGwIds2,
        array $extraOverrides = [],
    ): User {
        $baseToken = array_merge([
            'sub'                => "test-relogin-{$identity}",
            'email'              => "{$identity}@example.com",
            'preferred_username' => $identity,
        ], $extraOverrides);

        $ro1 = $this->createReloginResourceOwner(array_merge($baseToken, [
            'organisation'     => $firstOrgs,
            'responsibilities' => $firstResps,
        ]));
        $user1 = $this->mapResourceOwnerToUser($ro1);
        $userId = $user1->getId();
        self::assertSame($expectedGwIds1, $this->getSortedGwIds($user1));

        $ro2 = $this->createReloginResourceOwner(array_merge($baseToken, [
            'organisation'     => $secondOrgs,
            'responsibilities' => $secondResps,
        ]));
        $user2 = $this->mapResourceOwnerToUser($ro2);

        self::assertSame($userId, $user2->getId(), self::MSG_SAME_USER);
        self::assertSame($expectedGwIds2, $this->getSortedGwIds($user2));

        return $user2;
    }

    // --- ID changes: user switches organisation/responsibility ---

    /** User switches to completely different affiliations. */
    public function testReloginAffiliationIdsChange(): void
    {
        $this->assertReloginSyncsOrgs(
            'aff-change',
            [['id' => 'AMT-A', 'name' => 'Amt A'], ['id' => 'AMT-B', 'name' => 'Amt B']],
            [],
            ['AMT-A', 'AMT-B'],
            [['id' => 'AMT-C', 'name' => 'Amt C'], ['id' => 'AMT-D', 'name' => 'Amt D']],
            [],
            ['AMT-C', 'AMT-D'],
        );
    }

    /** Responsibility IDs change (affiliations stay) → full org replacement. */
    public function testReloginResponsibilityIdsChange(): void
    {
        $this->assertReloginSyncsOrgs(
            'resp-change',
            [['id' => 'AMT-X', 'name' => 'Amt X']],
            [['id' => 'WATER', 'name' => 'Wasserwirtschaft'], ['id' => 'LITTER', 'name' => 'Abfallwirtschaft']],
            ['AMT-X.LITTER', 'AMT-X.WATER'],
            [['id' => 'AMT-X', 'name' => 'Amt X']],
            [['id' => 'ENERGY', 'name' => 'Energiewirtschaft'], ['id' => 'FOREST', 'name' => 'Forstwirtschaft']],
            ['AMT-X.ENERGY', 'AMT-X.FOREST'],
        );
    }

    /** Both affiliation and responsibility IDs change simultaneously → 2×2 = 4 orgs. */
    public function testReloginBothAffiliationAndResponsibilityIdsChange(): void
    {
        $this->assertReloginSyncsOrgs(
            'both-change',
            [['id' => 'OLD-A', 'name' => 'Old Amt']],
            [['id' => 'OLD-R', 'name' => 'Old Responsibility']],
            ['OLD-A.OLD-R'],
            [['id' => 'NEW-A1', 'name' => 'New Amt 1'], ['id' => 'NEW-A2', 'name' => 'New Amt 2']],
            [['id' => 'NEW-R1', 'name' => 'New Resp 1'], ['id' => 'NEW-R2', 'name' => 'New Resp 2']],
            ['NEW-A1.NEW-R1', 'NEW-A1.NEW-R2', 'NEW-A2.NEW-R1', 'NEW-A2.NEW-R2'],
        );
    }

    // --- Name changes: IDs stay the same, names differ ---

    /** Affiliation names change but IDs stay → names must NOT be overwritten. */
    public function testReloginAffiliationNamesChangeButIdsStay(): void
    {
        $user = $this->assertReloginSyncsOrgs(
            'aff-name',
            [['id' => 'STABLE-A', 'name' => self::ORG_NAME_AMT_A], ['id' => 'STABLE-B', 'name' => self::ORG_NAME_AMT_B]],
            [],
            ['STABLE-A', 'STABLE-B'],
            [['id' => 'STABLE-A', 'name' => 'Renamed Amt A'], ['id' => 'STABLE-B', 'name' => 'Renamed Amt B']],
            [],
            ['STABLE-A', 'STABLE-B'],
        );
        self::assertSame(
            [self::ORG_NAME_AMT_A, self::ORG_NAME_AMT_B],
            $this->getSortedOrgNames($user),
            'Org names must NOT be overwritten on re-login'
        );
    }

    /** Responsibility names change but IDs stay → cartesian gwIds unchanged, names preserved. */
    public function testReloginResponsibilityNamesChangeButIdsStay(): void
    {
        $user = $this->assertReloginSyncsOrgs(
            'resp-name',
            [['id' => 'FIX-AMT', 'name' => 'Fixed Amt']],
            [['id' => 'R1', 'name' => 'Original Resp 1'], ['id' => 'R2', 'name' => 'Original Resp 2']],
            ['FIX-AMT.R1', 'FIX-AMT.R2'],
            [['id' => 'FIX-AMT', 'name' => 'Fixed Amt']],
            [['id' => 'R1', 'name' => 'Renamed Resp 1'], ['id' => 'R2', 'name' => 'Renamed Resp 2']],
            ['FIX-AMT.R1', 'FIX-AMT.R2'],
        );
        self::assertSame(
            ['Fixed Amt - Original Resp 1', 'Fixed Amt - Original Resp 2'],
            $this->getSortedOrgNames($user),
            'Org names must NOT be overwritten on re-login'
        );
    }

    // --- Org count changes: affiliations added or removed ---

    /** User gains an additional affiliation on re-login. */
    public function testReloginGainsAdditionalAffiliation(): void
    {
        $this->assertReloginSyncsOrgs(
            'gain-aff',
            [['id' => 'KEEP-ORG', 'name' => 'Kept Organisation']],
            [],
            ['KEEP-ORG'],
            [['id' => 'KEEP-ORG', 'name' => 'Kept Organisation'], ['id' => 'NEW-ORG', 'name' => 'New Organisation']],
            [],
            ['KEEP-ORG', 'NEW-ORG'],
        );
    }

    /** User loses an affiliation on re-login. */
    public function testReloginLosesAffiliation(): void
    {
        $this->assertReloginSyncsOrgs(
            'lose-aff',
            [['id' => 'ORG-STAY-1', 'name' => 'Stay 1'], ['id' => 'ORG-STAY-2', 'name' => 'Stay 2'], ['id' => 'ORG-GONE', 'name' => 'Will Be Removed']],
            [],
            ['ORG-GONE', 'ORG-STAY-1', 'ORG-STAY-2'],
            [['id' => 'ORG-STAY-1', 'name' => 'Stay 1'], ['id' => 'ORG-STAY-2', 'name' => 'Stay 2']],
            [],
            ['ORG-STAY-1', 'ORG-STAY-2'],
        );
    }

    // --- Mode switches: cartesian ↔ affiliations-only ↔ single-org ---

    /** Switch from cartesian to affiliations-only (responsibilities removed). */
    public function testReloginSwitchFromCartesianToAffiliationsOnly(): void
    {
        $this->assertReloginSyncsOrgs(
            'cart-to-aff',
            [['id' => 'CT-AMT-A', 'name' => 'CT Amt A'], ['id' => 'CT-AMT-B', 'name' => 'CT Amt B']],
            [['id' => 'CT-RESP', 'name' => 'CT Responsibility']],
            ['CT-AMT-A.CT-RESP', 'CT-AMT-B.CT-RESP'],
            [['id' => 'CT-AMT-A', 'name' => 'CT Amt A'], ['id' => 'CT-AMT-B', 'name' => 'CT Amt B']],
            [],
            ['CT-AMT-A', 'CT-AMT-B'],
        );
    }

    /** Switch from affiliations-only to cartesian (responsibilities added). */
    public function testReloginSwitchFromAffiliationsOnlyToCartesian(): void
    {
        $this->assertReloginSyncsOrgs(
            'aff-to-cart',
            [['id' => 'AC-AMT', 'name' => 'AC Amt']],
            [],
            ['AC-AMT'],
            [['id' => 'AC-AMT', 'name' => 'AC Amt']],
            [['id' => 'AC-WATER', 'name' => 'Wasser'], ['id' => 'AC-LITTER', 'name' => 'Abfall']],
            ['AC-AMT.AC-LITTER', 'AC-AMT.AC-WATER'],
        );
    }

    /** Switch from multi-org to single organisationId fallback. */
    public function testReloginSwitchFromMultiOrgToSingleOrgFallback(): void
    {
        $user = $this->assertReloginSyncsOrgs(
            'multi-to-single',
            [['id' => 'MTS-ORG-A', 'name' => 'MTS Org A'], ['id' => 'MTS-ORG-B', 'name' => 'MTS Org B']],
            [],
            ['MTS-ORG-A', 'MTS-ORG-B'],
            [],
            [],
            ['MTS-FALLBACK'],
            ['organisationId' => 'MTS-FALLBACK', 'organisationName' => 'MTS Fallback Org'],
        );
        self::assertCount(1, $user->getOrganisations());
    }

    /** Switch from single organisationId fallback to multi-org cartesian. */
    public function testReloginSwitchFromSingleOrgFallbackToCartesian(): void
    {
        $this->assertReloginSyncsOrgs(
            'single-to-cart',
            [],
            [],
            ['STC-LEGACY'],
            [['id' => 'STC-AMT', 'name' => 'STC Amt']],
            [['id' => 'STC-R1', 'name' => 'STC Resp 1'], ['id' => 'STC-R2', 'name' => 'STC Resp 2']],
            ['STC-AMT.STC-R1', 'STC-AMT.STC-R2'],
            ['organisationId' => 'STC-LEGACY', 'organisationName' => 'STC Legacy Org'],
        );
    }

    // --- Partial overlap: some IDs stay, some change ---

    /** Partial affiliation overlap: one kept, one swapped. */
    public function testReloginPartialAffiliationOverlap(): void
    {
        $this->assertReloginSyncsOrgs(
            'partial',
            [['id' => 'PO-KEEP', 'name' => 'Partial Keep'], ['id' => 'PO-OLD', 'name' => 'Partial Old']],
            [],
            ['PO-KEEP', 'PO-OLD'],
            [['id' => 'PO-KEEP', 'name' => 'Partial Keep'], ['id' => 'PO-NEW', 'name' => 'Partial New']],
            [],
            ['PO-KEEP', 'PO-NEW'],
        );
    }

    /** Cartesian partial overlap: one responsibility swapped. */
    public function testReloginCartesianPartialResponsibilityOverlap(): void
    {
        $this->assertReloginSyncsOrgs(
            'cart-partial',
            [['id' => 'CP-AMT', 'name' => 'CP Amt']],
            [['id' => 'CP-R-KEEP', 'name' => 'Keep Resp'], ['id' => 'CP-R-OLD', 'name' => 'Old Resp']],
            ['CP-AMT.CP-R-KEEP', 'CP-AMT.CP-R-OLD'],
            [['id' => 'CP-AMT', 'name' => 'CP Amt']],
            [['id' => 'CP-R-KEEP', 'name' => 'Keep Resp'], ['id' => 'CP-R-NEW', 'name' => 'New Resp']],
            ['CP-AMT.CP-R-KEEP', 'CP-AMT.CP-R-NEW'],
        );
    }
}
