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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class StatementGroupResourceApiTest extends AbstractApiTest
{
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
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_statement_cluster']);

        $response = $this->sendRequest(
            '/api/3.0/StatementGroup/'.$group->getId(),
            'PATCH',
            $user,
            $procedure,
            ['data' => [
                'type'       => 'StatementGroup',
                'id'         => $group->getId(),
                'attributes' => ['groupName' => 'New Name'],
            ]]
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('New Name', $this->fetchPersistedGroup($group->getId())->getName());
    }

    public function testPatchWithHeadStatementIdIsRejected(): void
    {
        $procedure = ProcedureFactory::createOne();
        [$group, , $member2] = $this->createGroupWithTwoMembers($procedure);
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_statement_cluster']);

        $response = $this->sendRequest(
            '/api/3.0/StatementGroup/'.$group->getId(),
            'PATCH',
            $user,
            $procedure,
            ['data' => [
                'type'       => 'StatementGroup',
                'id'         => $group->getId(),
                'attributes' => [
                    'groupName'       => 'New Name',
                    'headStatementId' => $member2->getId(),
                ],
            ]]
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Old Name', $this->fetchPersistedGroup($group->getId())->getName());
    }

    public function testPatchWithStatementsIsIgnored(): void
    {
        $procedure = ProcedureFactory::createOne();
        [$group] = $this->createGroupWithTwoMembers($procedure);
        $otherStatement = StatementFactory::createOne(['procedure' => $procedure]);
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_statement_cluster']);

        $response = $this->sendRequest(
            '/api/3.0/StatementGroup/'.$group->getId(),
            'PATCH',
            $user,
            $procedure,
            ['data' => [
                'type'       => 'StatementGroup',
                'id'         => $group->getId(),
                'attributes' => [
                    'groupName'  => 'New Name',
                    'statements' => [['id' => $otherStatement->getId(), 'externId' => $otherStatement->getExternId()]],
                ],
            ]]
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $persistedGroup = $this->fetchPersistedGroup($group->getId());
        self::assertSame('New Name', $persistedGroup->getName());
        self::assertCount(2, $persistedGroup->getCluster());
    }
}
