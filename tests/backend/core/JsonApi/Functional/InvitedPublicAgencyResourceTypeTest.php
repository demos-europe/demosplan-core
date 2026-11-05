<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitedPublicAgencyResourceType;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\JsonApiTest;

/**
 * The institutions of a procedure carry the flag `hasActiveUsers`: true if at least one of their users
 * is not deleted and holds an institution role in the current customer.
 */
class InvitedPublicAgencyResourceTypeTest extends JsonApiTest
{
    private ?Customer $currentCustomer = null;
    private ?Procedure $procedure = null;
    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentCustomer = CustomerFactory::createOne(['subdomain' => 'invited-'.uniqid()])->_real();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($this->currentCustomer->getSubdomain());
        $procedureReference = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $this->procedure = $this->getApiEntityManager()->find(Procedure::class, $procedureReference->getId());
        $this->user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions([
            'feature_json_api_list',
            'area_main_procedures',
            'area_admin_invitable_institution',
        ]);
    }

    public function testListFlagsInvitedInstitutionsByTheirActiveInstitutionUsers(): void
    {
        // Arrange
        $withInstitutionUser = $this->createInvitedOrga(RoleInterface::PUBLIC_AGENCY_WORKER);
        $withPlanningAgencyAdminOnly = $this->createInvitedOrga(RoleInterface::PLANNING_AGENCY_ADMIN);
        $withDeletedInstitutionUser = $this->createInvitedOrga(RoleInterface::PUBLIC_AGENCY_COORDINATION, deleted: true);

        // Act
        $response = $this->executeListRequest(
            InvitedPublicAgencyResourceType::getName(),
            $this->user,
            $this->procedure,
            urlParameters: ['fields' => [InvitedPublicAgencyResourceType::getName() => 'legalName,hasActiveUsers']]
        );

        // Assert
        $expected = [
            $withInstitutionUser->getId()         => true,
            $withPlanningAgencyAdminOnly->getId() => false,
            $withDeletedInstitutionUser->getId()  => false,
        ];
        ksort($expected);
        self::assertSame($expected, $this->getFlagsById($response));
    }

    public function testListIncludesTheFlagByDefault(): void
    {
        // Arrange
        $orga = $this->createInvitedOrga(RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $response = $this->executeListRequest(InvitedPublicAgencyResourceType::getName(), $this->user, $this->procedure);

        // Assert
        self::assertSame([$orga->getId() => true], $this->getFlagsById($response));
    }

    public function testListOmitsTheFlagIfOtherFieldsAreRequested(): void
    {
        // Arrange
        $this->createInvitedOrga(RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $response = $this->executeListRequest(
            InvitedPublicAgencyResourceType::getName(),
            $this->user,
            $this->procedure,
            urlParameters: ['fields' => [InvitedPublicAgencyResourceType::getName() => 'legalName']]
        );

        // Assert
        self::assertCount(1, $response['data']);
        self::assertSame(['legalName'], array_keys($response['data'][0]['attributes']));
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, bool> the flag of every listed institution, by id and sorted by id, because the list
     *                             itself is sorted by the random names of the test institutions
     */
    private function getFlagsById(array $response): array
    {
        $flags = [];
        foreach ($response['data'] as $resource) {
            $flags[$resource['id']] = $resource['attributes']['hasActiveUsers'];
        }
        ksort($flags);

        return $flags;
    }

    /**
     * Creates an institution of the current customer with one user holding the given role and invites it
     * to the procedure of the test.
     */
    private function createInvitedOrga(string $roleCode, bool $deleted = false): Orga
    {
        $orga = OrgaFactory::createOne(['deleted' => false, 'showlist' => true])->_real();
        $statusInCustomer = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $this->currentCustomer,
        ])->_real();
        $orga->addStatusInCustomer($statusInCustomer);

        $role = $this->getContainer()->get(RoleHandler::class)->getUserRolesByCodes([$roleCode])[0];
        $user = UserFactory::createOne(['deleted' => $deleted])->_real();
        $orga->addUser($user);
        $user->addDplanrole($role, $this->currentCustomer);

        $this->procedure->addOrganisation($orga);
        $this->getApiEntityManager()->flush();

        return $orga;
    }

    /**
     * The kernel is rebooted after the fixtures are loaded, see AbstractApiTest::setUp(). Only the
     * entity manager of the current container knows the entities created in this test.
     */
    private function getApiEntityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }
}
