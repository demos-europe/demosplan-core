<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\ApiResources\StatementGroupResource;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\AbstractApiTest;

class StatementGroupResourceApiTest extends AbstractApiTest
{
    private const GROUP_URI_PREFIX = '/api/3.0/StatementGroup/';

    private const NEW_GROUP_NAME = 'New Name';

    /**
     * @return array{Statement, Statement, Statement}
     */
    private function createGroupWithTwoMembers(Procedure $procedure): array
    {
        $member1 = StatementFactory::createOne(['procedure' => $procedure]);
        $member2 = StatementFactory::createOne(['procedure' => $procedure]);

        /** @var StatementHandler $statementHandler */
        $statementHandler = $this->getContainer()->get(StatementHandler::class);
        $group = $statementHandler->createStatementCluster(
            $procedure->getId(),
            [$member1->getId(), $member2->getId()],
            $member1->getId(),
            'Old Name'
        );

        return [$group, $member1, $member2];
    }

    /**
     * /api/3.0/* routes sit behind the `api_platform` firewall (context: main, form-login
     * authenticator), not the stateless JWT `api` firewall AbstractApiTest::sendRequest() targets —
     * so authentication needs the session-based test login, not an X-JWT-Authorization header.
     */
    private function loginUserForApiPlatform(User $user): void
    {
        $this->client->loginUser($user, 'main');
    }

    private function fetchPersistedGroup(string $groupId): Statement
    {
        $this->getEntityManager()->clear();
        /** @var StatementRepository $statementRepository */
        $statementRepository = $this->getContainer()->get(StatementRepository::class);
        $group = $statementRepository->get($groupId);
        self::assertInstanceOf(Statement::class, $group);

        return $group;
    }

    public function testPatchGroupNameOnlyUpdatesAndPersists(): void
    {
        $procedure = ProcedureFactory::createOne();
        [$group] = $this->createGroupWithTwoMembers($procedure);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_statement_cluster']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::GROUP_URI_PREFIX.$group->getId(),
            'PATCH',
            $user,
            $procedure,
            ['data' => [
                'type'       => 'StatementGroup',
                'id'         => $group->getId(),
                'attributes' => ['groupName' => self::NEW_GROUP_NAME],
            ]]
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(self::NEW_GROUP_NAME, $this->fetchPersistedGroup($group->getId())->getName());
    }

    public function testHeadStatementIdCannotBeChangedViaPatch(): void
    {
        $resource = new StatementGroupResource();
        $resource->groupName = self::NEW_GROUP_NAME;
        $resource->headStatementId = 'another-statement-id';

        /** @var ValidatorInterface $validator */
        $validator = $this->getContainer()->get(ValidatorInterface::class);
        $violations = $validator->validate($resource, null, ['statementgroup:update']);

        self::assertGreaterThan(0, count($violations));
        self::assertSame(
            'headStatementId cannot be changed via PATCH.',
            $violations->get(0)->getMessage()
        );
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT'  => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ];
    }
}
