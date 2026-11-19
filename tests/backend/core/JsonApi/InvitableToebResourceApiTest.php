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
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * The institutions of the current customer that can be invited to the current procedure. The customer is created
 * for each test, so the lists contain exactly the institutions the test created.
 */
class InvitableToebResourceApiTest extends AbstractApiTest
{
    private const ROUTE = '/api/3.0/InvitableToeb';

    private ?User $user = null;
    private ?Customer $currentCustomer = null;
    private ?OrgaType $publicAgencyType = null;
    private ?Procedure $procedure = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentCustomer = CustomerFactory::createOne(['subdomain' => 'invitable-toeb-'.uniqid()])->_real();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($this->currentCustomer->getSubdomain());
        $this->user = $this->getUserWithRolesInCurrentCustomer();
        $this->publicAgencyType = OrgaTypeFactory::createOne(['name' => OrgaTypeInterface::PUBLIC_AGENCY])->_real();
        $this->procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
    }

    /**
     * The session login resolves the roles of the user in the current customer, so the user of the fixtures gets the
     * roles it has in the fixture customer in the customer of this test as well.
     */
    private function getUserWithRolesInCurrentCustomer(): User
    {
        $entityManager = $this->getApiEntityManager();
        $user = $entityManager->find(User::class, $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());
        $fixtureCustomer = $this->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY)->getStatusInCustomers()->first()->getCustomer();
        foreach ($user->getDplanroles($fixtureCustomer) as $role) {
            $user->addDplanrole($role, $this->currentCustomer);
        }
        $entityManager->flush();

        return $user;
    }

    /**
     * /api/3.0/* routes sit behind the session based `main` firewall, see TagResourceApiTest.
     */
    private function loginForApiPlatform(): void
    {
        $this->client->loginUser($this->user, 'main');
    }

    /**
     * Only the procedure header, the JWT header is meant for the stateless `api` firewall, see TagResourceApiTest.
     */
    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        return null === $procedure ? [] : ['HTTP_X_DEMOSPLAN_PROCEDURE_ID' => $procedure->getId()];
    }

    protected function getServerParameters(): array
    {
        return ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    }

    private function enableInvitablePermissions(): void
    {
        $this->enablePermissions(['area_main_procedures', 'area_admin_invitable_institution']);
        $this->loginForApiPlatform();
    }

    public function testListReturnsInstitutionWithItsAttributes(): void
    {
        // Arrange
        $orga = $this->createInvitableOrga(name: 'Amt fuer Test', roleCode: RoleInterface::PUBLIC_AGENCY_WORKER);
        $orga->setParticipationEmail('beteiligung@example.org');
        $this->getApiEntityManager()->flush();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertCount(1, $data);
        self::assertSame($orga->getId(), $data[0]['id']);
        self::assertSame('Amt fuer Test', $data[0]['attributes']['legalName']);
        self::assertSame('beteiligung@example.org', $data[0]['attributes']['participationFeedbackEmailAddress']);
        self::assertTrue($data[0]['attributes']['hasActiveUsers']);
    }

    public function testListReturnsHasActiveUsersFalseForInstitutionWithoutInstitutionUser(): void
    {
        // Arrange
        $this->createInvitableOrga();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertCount(1, $data);
        self::assertFalse($data[0]['attributes']['hasActiveUsers']);
    }

    public function testListHidesInstitutionsInvitedToTheProcedure(): void
    {
        // Arrange
        $invited = $this->createInvitableOrga();
        $notInvited = $this->createInvitableOrga();
        $this->procedure->addOrganisation($invited);
        $this->getApiEntityManager()->flush();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame([$notInvited->getId()], $this->getIds($response));
    }

    public function testListHidesInstitutionsThatAreNotListedNotAcceptedDeletedOrOfAnotherTypeOrCustomer(): void
    {
        // Arrange
        $listed = $this->createInvitableOrga();
        $this->createInvitableOrga(showlist: false);
        $this->createInvitableOrga(status: OrgaStatusInCustomerInterface::STATUS_PENDING);
        $this->createInvitableOrga(deleted: true);
        $this->createInvitableOrga(type: OrgaTypeFactory::createOne(['name' => OrgaTypeInterface::MUNICIPALITY])->_real());
        $this->createInvitableOrga(acceptedInCustomer: CustomerFactory::createOne(['subdomain' => 'other-'.uniqid()])->_real());
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame([$listed->getId()], $this->getIds($response));
    }

    public function testListIsEmptyWithoutProcedure(): void
    {
        // Arrange
        $this->createInvitableOrga();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, null);

        // Assert
        self::assertSame([], $this->getIds($response));
    }

    public function testListIsOrderedByNameAscending(): void
    {
        // Arrange: created in a different order than their names
        $c = $this->createInvitableOrga(name: 'C Amt');
        $a = $this->createInvitableOrga(name: 'A Amt');
        $b = $this->createInvitableOrga(name: 'B Amt');
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame([$a->getId(), $b->getId(), $c->getId()], $this->getIds($response));
    }

    public function testListIsPaginated(): void
    {
        // Arrange
        $this->createInvitableOrga(name: 'C Amt');
        $this->createInvitableOrga(name: 'A Amt');
        $b = $this->createInvitableOrga(name: 'B Amt');
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(self::ROUTE.'?pagination=true&itemsPerPage=1&page=2', 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        self::assertSame([$b->getId()], array_column($content['data'], 'id'));
        self::assertSame(3, $content['meta']['totalItems']);
        self::assertSame(1, $content['meta']['itemsPerPage']);
    }

    public function testListRestrictsAttributesToRequestedFields(): void
    {
        // Arrange
        $this->createInvitableOrga();
        $this->enableInvitablePermissions();

        // Act
        $response = $this->sendRequest(
            self::ROUTE.'?fields[InvitableToeb]=legalName,hasActiveUsers',
            'GET',
            $this->user,
            $this->procedure
        );

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertSame(['legalName', 'hasActiveUsers'], array_keys($data[0]['attributes']));
    }

    public function testListIsDeniedWithoutTheInvitableInstitutionArea(): void
    {
        // Arrange: only one of the two required permissions
        $this->createInvitableOrga();
        $this->enablePermissions(['area_main_procedures']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testGetReturnsInstitutionAndHidesInvitedOne(): void
    {
        // Arrange
        $notInvited = $this->createInvitableOrga(name: 'Amt fuer Get');
        $invited = $this->createInvitableOrga();
        $this->procedure->addOrganisation($invited);
        $this->getApiEntityManager()->flush();
        $this->enableInvitablePermissions();

        // Act
        $found = $this->sendRequest(self::ROUTE.'/'.$notInvited->getId(), 'GET', $this->user, $this->procedure);
        $hidden = $this->sendRequest(self::ROUTE.'/'.$invited->getId(), 'GET', $this->user, $this->procedure);

        // Assert
        self::assertSame(Response::HTTP_OK, $found->getStatusCode());
        self::assertSame('Amt fuer Get', Json::decodeToArray($found->getContent())['data']['attributes']['legalName']);
        self::assertSame(Response::HTTP_NOT_FOUND, $hidden->getStatusCode());
    }

    /**
     * @return list<string> the ids of the listed institutions, in the order of the response
     */
    private function getIds(Response $response): array
    {
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        return array_column(Json::decodeToArray($response->getContent())['data'], 'id');
    }

    /**
     * Creates an institution that can be invited: not deleted, listed and accepted as public agency in the current
     * customer, unless a parameter says otherwise. It has one user holding the given role in the current customer,
     * or no user at all if the role is null.
     */
    private function createInvitableOrga(
        ?string $name = null,
        ?string $roleCode = null,
        bool $deleted = false,
        bool $showlist = true,
        string $status = OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
        ?OrgaType $type = null,
        ?Customer $acceptedInCustomer = null,
    ): Orga {
        $orgaAttributes = ['deleted' => $deleted, 'showlist' => true];
        if (null !== $name) {
            $orgaAttributes['name'] = $name;
        }
        $orga = OrgaFactory::createOne($orgaAttributes)->_real();
        $statusInCustomer = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $acceptedInCustomer ?? $this->currentCustomer,
            'orgaType' => $type ?? $this->publicAgencyType,
            'status'   => $status,
            'showlist' => $showlist,
        ])->_real();
        $orga->addStatusInCustomer($statusInCustomer);

        if (null !== $roleCode) {
            $role = $this->getContainer()->get(RoleHandler::class)->getUserRolesByCodes([$roleCode])[0];
            $user = UserFactory::createOne(['deleted' => false])->_real();
            $orga->addUser($user);
            $user->addDplanrole($role, $this->currentCustomer);
        }
        $this->getApiEntityManager()->flush();

        return $orga;
    }

    /**
     * The kernel is rebooted after the fixtures are loaded, see AbstractApiTest::setUp(). Only the entity manager of
     * the current container knows the entities created in this test.
     */
    private function getApiEntityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }
}
