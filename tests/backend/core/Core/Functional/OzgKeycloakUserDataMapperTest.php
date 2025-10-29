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

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper\DepartmentMapper;
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
use Psr\Log\NullLogger;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\FunctionalTestCase;

class OzgKeycloakUserDataMapperTest extends FunctionalTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $departmentMapper = new DepartmentMapper(
            $this->getContainer()->get(EntityManagerInterface::class),
            new NullLogger()
        );

        $this->sut = new OzgKeycloakUserDataMapper(
            $this->getContainer()->get(CustomerService::class),
            $this->getContainer()->get(DepartmentRepository::class),
            $this->getContainer()->get(EntityManagerInterface::class),
            $this->getContainer()->get(GlobalConfig::class),
            new NullLogger(),
            $this->getContainer()->get(OrgaRepository::class),
            $this->getContainer()->get(OrgaService::class),
            $this->getContainer()->get(OrgaTypeRepository::class),
            $this->getContainer()->get(RoleRepository::class),
            $this->getContainer()->get(UserRepository::class),

            $this->getContainer()->get(UserRoleInCustomerRepository::class),
            $this->getContainer()->get(UserService::class),
            $this->getContainer()->get(ValidatorInterface::class),
            $departmentMapper
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
        $userData = new OzgKeycloakUserData(
            new NullLogger(),
            new ParameterBag([
                'keycloak_group_role_string' => 'PlaceholderForKeycloakForRole',
            ]),
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
}
