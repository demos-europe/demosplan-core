<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitableInstitutionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitablePublicAgencyResourceType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\FunctionalTestCase;
use Tests\Core\User\PublicAgencyOrgaTestTrait;

/**
 * An institution that is accepted in the current customer has only users without an institution role.
 * Whether it is listed depends on the parameter institution_list_allow_orgas_without_users, which is
 * read from the env var INSTITUTION_LIST_ALLOW_ORGAS_WITHOUT_USERS when the service is created. The
 * env var is therefore set before the kernel is booted.
 */
class InvitableOrgasWithoutInstitutionRoleTest extends FunctionalTestCase
{
    use PublicAgencyOrgaTestTrait;

    private const ENV_VAR = 'INSTITUTION_LIST_ALLOW_ORGAS_WITHOUT_USERS';

    protected ?string $previousEnvValue = null;

    protected function setUp(): void
    {
        $this->previousEnvValue = $_SERVER[self::ENV_VAR] ?? null;
        $allowOrgasWithoutUsers = $this->providedData()[0] ?? false;
        $value = $allowOrgasWithoutUsers ? 'true' : 'false';
        $_SERVER[self::ENV_VAR] = $_ENV[self::ENV_VAR] = $value;
        putenv(self::ENV_VAR.'='.$value);

        parent::setUp();

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);
        $this->enablePermissions([
            'area_main_procedures',
            'area_admin_invitable_institution',
            'feature_institution_tag_assign',
            'feature_institution_tag_read',
        ]);
    }

    protected function tearDown(): void
    {
        if (null === $this->previousEnvValue) {
            unset($_SERVER[self::ENV_VAR], $_ENV[self::ENV_VAR]);
            putenv(self::ENV_VAR);
        } else {
            $_SERVER[self::ENV_VAR] = $_ENV[self::ENV_VAR] = $this->previousEnvValue;
            putenv(self::ENV_VAR.'='.$this->previousEnvValue);
        }

        parent::tearDown();
    }

    #[DataProvider('allowOrgasWithoutUsersProvider')]
    public function testInstitutionListContainsAcceptedOrgaWithoutInstitutionRoleOnlyIfAllowed(bool $allowOrgasWithoutUsers): void
    {
        // Arrange
        $customer = $this->createCurrentCustomer();
        $orga = $this->createPublicAgencyWithOnlyPlanningAgencyAdminUser($customer);
        $sut = $this->getContainer()->get(InvitableInstitutionResourceType::class);

        // Act
        $listedOrgaIds = $this->getIds($sut->getEntities([], []));

        // Assert
        self::assertSame($allowOrgasWithoutUsers, in_array($orga->getId(), $listedOrgaIds, true));
    }

    #[DataProvider('allowOrgasWithoutUsersProvider')]
    public function testInvitablePublicAgencyListContainsAcceptedOrgaWithoutInstitutionRoleOnlyIfAllowed(bool $allowOrgasWithoutUsers): void
    {
        // Arrange
        $customer = $this->createCurrentCustomer();
        $orga = $this->createPublicAgencyWithOnlyPlanningAgencyAdminUser($customer);
        $this->getContainer()->get(CurrentProcedureService::class)->setProcedure(
            $this->getProcedureReference('testProcedure')
        );
        $sut = $this->getContainer()->get(InvitablePublicAgencyResourceType::class);

        // Act
        $listedOrgaIds = $this->getIds($sut->getEntities([], []));

        // Assert
        self::assertSame($allowOrgasWithoutUsers, in_array($orga->getId(), $listedOrgaIds, true));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function allowOrgasWithoutUsersProvider(): array
    {
        return [
            'not allowed' => [false],
            'allowed'     => [true],
        ];
    }

    private function createCurrentCustomer(): Customer
    {
        $customer = CustomerFactory::createOne(['subdomain' => 'current-customer-'.uniqid()])->_real();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($customer->getSubdomain());

        return $customer;
    }

    /**
     * @param iterable<Orga> $orgas
     *
     * @return list<string>
     */
    private function getIds(iterable $orgas): array
    {
        $ids = [];
        foreach ($orgas as $orga) {
            $ids[] = $orga->getId();
        }

        return $ids;
    }
}
