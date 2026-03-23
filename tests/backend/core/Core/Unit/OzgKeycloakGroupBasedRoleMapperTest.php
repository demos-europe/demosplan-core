<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakGroupBasedRoleMapper;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class OzgKeycloakGroupBasedRoleMapperTest extends TestCase
{
    private const ROLE_NAME_PLANNING_ADMIN = 'Fachplanung Administration';
    private const ROLE_NAME_SUPPORT = 'Support';
    private const ROLE_NAME_PUBLIC_AGENCY_WORKER = 'Institutions Sachbearbeitung';
    private const TEST_CUSTOMER = 'testcustomer';

    private OzgKeycloakGroupBasedRoleMapper $sut;
    private MockObject&GlobalConfig $globalConfig;
    private MockObject&RoleRepository $roleRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalConfig = $this->createMock(GlobalConfig::class);
        $this->roleRepository = $this->createMock(RoleRepository::class);

        $this->sut = new OzgKeycloakGroupBasedRoleMapper(
            $this->globalConfig,
            new NullLogger(),
            $this->roleRepository
        );
    }

    public function testExtractRoleCodesFromGroupsWithRecognizedRoles(): void
    {
        $rolesOfCustomer = [
            self::TEST_CUSTOMER => [
                self::ROLE_NAME_PLANNING_ADMIN,
                self::ROLE_NAME_PUBLIC_AGENCY_WORKER,
            ],
        ];

        [$recognizedRoleCodes, $unIdentifiedRoles] = $this->sut->extractRoleCodesFromGroups(
            $rolesOfCustomer,
            self::TEST_CUSTOMER
        );

        self::assertCount(2, $recognizedRoleCodes);
        self::assertContains(RoleInterface::PLANNING_AGENCY_ADMIN, $recognizedRoleCodes);
        self::assertContains(RoleInterface::PUBLIC_AGENCY_WORKER, $recognizedRoleCodes);
        self::assertEmpty($unIdentifiedRoles);
    }

    public function testExtractRoleCodesFromGroupsWithUnrecognizedRoles(): void
    {
        $rolesOfCustomer = [
            self::TEST_CUSTOMER => [
                self::ROLE_NAME_PLANNING_ADMIN,
                'Unknown Role Name',
                'Another Unknown',
            ],
        ];

        [$recognizedRoleCodes, $unIdentifiedRoles] = $this->sut->extractRoleCodesFromGroups(
            $rolesOfCustomer,
            self::TEST_CUSTOMER
        );

        self::assertCount(1, $recognizedRoleCodes);
        self::assertContains(RoleInterface::PLANNING_AGENCY_ADMIN, $recognizedRoleCodes);
        self::assertCount(2, $unIdentifiedRoles);
        self::assertContains('Unknown Role Name', $unIdentifiedRoles);
        self::assertContains('Another Unknown', $unIdentifiedRoles);
    }

    public function testExtractRoleCodesFromGroupsWithMissingSubdomain(): void
    {
        $rolesOfCustomer = [
            'othercustomer' => [
                self::ROLE_NAME_PLANNING_ADMIN,
            ],
        ];

        [$recognizedRoleCodes, $unIdentifiedRoles] = $this->sut->extractRoleCodesFromGroups(
            $rolesOfCustomer,
            'nonexistent'
        );

        self::assertEmpty($recognizedRoleCodes);
        self::assertEmpty($unIdentifiedRoles);
    }

    public function testExtractRoleCodesFromGroupsWithEmptyRoleNames(): void
    {
        $rolesOfCustomer = [
            self::TEST_CUSTOMER => [
                self::ROLE_NAME_PLANNING_ADMIN,
                '',
                null,
                self::ROLE_NAME_SUPPORT,
            ],
        ];

        [$recognizedRoleCodes, $unIdentifiedRoles] = $this->sut->extractRoleCodesFromGroups(
            $rolesOfCustomer,
            self::TEST_CUSTOMER
        );

        self::assertCount(2, $recognizedRoleCodes);
        self::assertContains(RoleInterface::PLANNING_AGENCY_ADMIN, $recognizedRoleCodes);
        self::assertContains(RoleInterface::PLATFORM_SUPPORT, $recognizedRoleCodes);
        self::assertEmpty($unIdentifiedRoles);
    }

    public function testMapGroupBasedRolesWithValidRoles(): void
    {
        $rolesOfCustomer = [
            self::TEST_CUSTOMER => [
                self::ROLE_NAME_PLANNING_ADMIN,
                self::ROLE_NAME_SUPPORT,
            ],
        ];

        $mockRole1 = $this->createMock(Role::class);
        $mockRole2 = $this->createMock(Role::class);

        $this->globalConfig->method('getRolesAllowed')
            ->willReturn([
                RoleInterface::PLANNING_AGENCY_ADMIN,
                RoleInterface::PLATFORM_SUPPORT,
            ]);

        $this->roleRepository->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($mockRole1, $mockRole2) {
                if (RoleInterface::PLANNING_AGENCY_ADMIN === $criteria['code']) {
                    return $mockRole1;
                }
                if (RoleInterface::PLATFORM_SUPPORT === $criteria['code']) {
                    return $mockRole2;
                }

                return null;
            });

        $result = $this->sut->mapGroupBasedRoles($rolesOfCustomer, self::TEST_CUSTOMER);

        self::assertCount(2, $result);
        self::assertContains($mockRole1, $result);
        self::assertContains($mockRole2, $result);
    }

    public function testMapGroupBasedRolesThrowsExceptionWhenNoRolesIdentified(): void
    {
        $rolesOfCustomer = [
            self::TEST_CUSTOMER => [
                'Unknown Role',
            ],
        ];

        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage('no roles could be identified');

        $this->sut->mapGroupBasedRoles($rolesOfCustomer, self::TEST_CUSTOMER);
    }

    public function testMapGroupBasedRolesFiltersUnavailableRoles(): void
    {
        $rolesOfCustomer = [
            self::TEST_CUSTOMER => [
                self::ROLE_NAME_PLANNING_ADMIN,
                self::ROLE_NAME_SUPPORT,
            ],
        ];

        $mockRole1 = $this->createMock(Role::class);

        // Only allow PLANNING_AGENCY_ADMIN, not PLATFORM_SUPPORT
        $this->globalConfig->method('getRolesAllowed')
            ->willReturn([
                RoleInterface::PLANNING_AGENCY_ADMIN,
            ]);

        $this->roleRepository->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($mockRole1) {
                if (RoleInterface::PLANNING_AGENCY_ADMIN === $criteria['code']) {
                    return $mockRole1;
                }

                return null;
            });

        $result = $this->sut->mapGroupBasedRoles($rolesOfCustomer, self::TEST_CUSTOMER);

        self::assertCount(1, $result);
        self::assertContains($mockRole1, $result);
    }

    public function testAllRoleTitlesMapToValidCodes(): void
    {
        $expectedMappings = [
            'Mandanten Administration'     => RoleInterface::CUSTOMER_MASTER_USER,
            'Organisationsadministration'  => RoleInterface::ORGANISATION_ADMINISTRATION,
            'Fachplanung Planungsbüro'     => RoleInterface::PRIVATE_PLANNING_AGENCY,
            'Fachplanung Administration'   => RoleInterface::PLANNING_AGENCY_ADMIN,
            'Fachplanung Sachbearbeitung'  => RoleInterface::PLANNING_AGENCY_WORKER,
            'Institutions Koordination'    => RoleInterface::PUBLIC_AGENCY_COORDINATION,
            'Institutions Sachbearbeitung' => RoleInterface::PUBLIC_AGENCY_WORKER,
            'Support'                      => RoleInterface::PLATFORM_SUPPORT,
            'Redaktion'                    => RoleInterface::CONTENT_EDITOR,
            'Privatperson-Angemeldet'      => RoleInterface::CITIZEN,
            'Fachliche Leitstelle'         => RoleInterface::PROCEDURE_CONTROL_UNIT,
            'Datenerfassung'               => RoleInterface::PROCEDURE_DATA_INPUT,
        ];

        foreach ($expectedMappings as $roleTitle => $expectedCode) {
            $rolesOfCustomer = ['test' => [$roleTitle]];
            [$recognizedRoleCodes, $unIdentifiedRoles] = $this->sut->extractRoleCodesFromGroups(
                $rolesOfCustomer,
                'test'
            );

            self::assertCount(1, $recognizedRoleCodes, "Role title '{$roleTitle}' should be recognized");
            self::assertContains($expectedCode, $recognizedRoleCodes, "Role title '{$roleTitle}' should map to '{$expectedCode}'");
            self::assertEmpty($unIdentifiedRoles, "Role title '{$roleTitle}' should not be in unidentified roles");
        }
    }
}
